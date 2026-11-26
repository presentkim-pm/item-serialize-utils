<?php

/**
 *
 *  ____                           _   _  ___
 * |  _ \ _ __ ___  ___  ___ _ __ | |_| |/ (_)_ __ ___
 * | |_) | '__/ _ \/ __|/ _ \ '_ \| __| ' /| | '_ ` _ \
 * |  __/| | |  __/\__ \  __/ | | | |_| . \| | | | | | |
 * |_|   |_|  \___||___/\___|_| |_|\__|_|\_\_|_| |_| |_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the MIT License. see <https://opensource.org/licenses/MIT>.
 *
 * @author       PresentKim (debe3721@gmail.com)
 * @link         https://github.com/PresentKim
 * @license      https://opensource.org/licenses/MIT MIT License
 *
 *   (\ /)
 *  ( . .) ♥
 *  c(")(")
 *
 * @noinspection PhpUnused
 */


declare(strict_types=1);

namespace kim\present\utils\itemserialize;

use kim\present\serializer\nbt\NbtSerializer;
use pocketmine\data\bedrock\item\SavedItemData;
use pocketmine\data\bedrock\item\SavedItemStackData;
use pocketmine\data\SavedDataLoadingException;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\Tag;

use function array_is_list;
use function array_map;
use function get_debug_type;
use function is_array;
use function is_int;
use function is_string;
use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * JSON format. An item is a JSON object:
 * - `Count`, `Damage` (integers), `Name` (string)
 * - `Block`, `tag` (SNBT strings, optional)
 * - `Slot` (integer, only in {@link serializeContents()} output)
 * - any other top-level NBT key of the item as SNBT string (so no data is dropped)
 *
 * A list (and contents) is a JSON array of item objects.
 * Not compatible with {@link \pocketmine\item\Item::legacyJsonDeserialize()}.
 */
final class JsonItemSerializer implements ItemSerializer{
    use ItemSerializerTrait;

    /** Keys written first and in this order, kept from older versions */
    private const ORDERED_KEYS = [
        SavedItemStackData::TAG_COUNT,
        SavedItemData::TAG_NAME,
        SavedItemData::TAG_DAMAGE,
        SavedItemData::TAG_BLOCK,
        SavedItemData::TAG_TAG,
    ];

    /** @throws \JsonException if the item contains strings that are not valid UTF-8 */
    protected static function serializeTag(Tag $tag) : string{
        $json = $tag instanceof ListTag
            ? array_map(self::tagToJson(...), $tag->getValue())
            : self::tagToJson($tag);
        return json_encode($json, JSON_THROW_ON_ERROR);
    }

    protected static function deserializeTag(string $contents) : Tag{
        try{
            $json = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        }catch(\JsonException $e){
            throw new SavedDataLoadingException("Invalid JSON: " . $e->getMessage(), 0, $e);
        }
        if(!is_array($json)){
            throw new SavedDataLoadingException("Expected JSON object or array, got " . get_debug_type($json));
        }
        if(!array_is_list($json)){
            return self::jsonToTag($json);
        }
        $list = new ListTag([], NBT::TAG_Compound);
        foreach($json as $entry){
            if(!is_array($entry) || ($entry !== [] && array_is_list($entry))){
                throw new SavedDataLoadingException("Expected JSON object item, got " . get_debug_type($entry));
            }
            $list->push(self::jsonToTag($entry));
        }
        return $list;
    }

    /** @return array<string, mixed> */
    private static function tagToJson(Tag $tag) : array{
        if(!($tag instanceof CompoundTag)){
            throw new \InvalidArgumentException("Expected CompoundTag item, got " . get_debug_type($tag));
        }
        $json = [];
        $values = $tag->getValue();
        foreach(self::ORDERED_KEYS as $key){
            if(isset($values[$key])){
                $json[$key] = self::valueToJson($key, $values[$key]);
                unset($values[$key]);
            }
        }
        foreach($values as $key => $value){
            $json[(string) $key] = self::valueToJson((string) $key, $value);
        }
        return $json;
    }

    private static function valueToJson(string $key, Tag $value) : mixed{
        return match($key){
            SavedItemStackData::TAG_COUNT,
            SavedItemData::TAG_DAMAGE,
            SavedItemData::TAG_NAME,
            SavedItemStackData::TAG_SLOT => $value->getValue(),
            default => NbtSerializer::toSnbt($value)
        };
    }

    /** @param array<mixed> $json */
    private static function jsonToTag(array $json) : CompoundTag{
        $tag = new CompoundTag();
        $tag->setByte(SavedItemStackData::TAG_COUNT, self::requireInt($json, SavedItemStackData::TAG_COUNT));
        $tag->setString(SavedItemData::TAG_NAME, self::requireString($json, SavedItemData::TAG_NAME));
        $tag->setShort(SavedItemData::TAG_DAMAGE, self::requireInt($json, SavedItemData::TAG_DAMAGE));
        unset($json[SavedItemStackData::TAG_COUNT], $json[SavedItemData::TAG_NAME], $json[SavedItemData::TAG_DAMAGE]);

        foreach($json as $key => $value){
            $key = (string) $key;
            if($key === SavedItemStackData::TAG_SLOT){
                $tag->setInt($key, self::requireInt($json, $key));
            }else{
                $tag->setTag($key, NbtSerializer::fromSnbt(self::requireString($json, $key)));
            }
        }
        return $tag;
    }

    /** @param array<mixed> $json */
    private static function requireInt(array $json, string $key) : int{
        $value = $json[$key] ?? null;
        if(!is_int($value)){
            throw new SavedDataLoadingException("\"$key\" must be an integer, got " . get_debug_type($value));
        }
        return $value;
    }

    /** @param array<mixed> $json */
    private static function requireString(array $json, string $key) : string{
        $value = $json[$key] ?? null;
        if(!is_string($value)){
            throw new SavedDataLoadingException("\"$key\" must be a string, got " . get_debug_type($value));
        }
        return $value;
    }

}

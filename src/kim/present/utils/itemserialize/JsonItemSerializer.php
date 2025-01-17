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
use pocketmine\item\Item;
use pocketmine\nbt\tag\CompoundTag;

use function array_map;
use function json_decode;
use function json_encode;

final class JsonItemSerializer implements ItemSerializer{
    use ItemSerializerTrait;

    /**
     * Serialize the item to the JSON contents, It's not compatible with {@link Item::jsonSerialize()}
     *
     * @param Item $item
     *
     * @return string JSON contents
     */
    public static function serialize(Item $item) : string{
        return self::encodeToUTF8(json_encode(self::serializeItemJson($item)));
    }

    /**
     * Deserialize the item from the JSON contents, It's not compatible with {@link Item::legacyJsonDeserialize()}
     *
     * @param string $contents JSON contents
     *
     * @return Item
     */
    public static function deserialize(string $contents) : Item{
        return self::deserializeItemJson(json_decode($contents, true));
    }

    /**
     * Serialize the item list to the contents
     *
     * @param Item[]                 $items
     *
     * @phpstan-param iterable<Item> $items
     *
     * @return string
     */
    public static function serializeList(iterable $items) : string{
        return self::encodeToUTF8(json_encode(array_map(self::serializeItemJson(...), $items)));
    }

    /**
     * Deserialize the item list from the contents
     *
     * @param string $contents
     *
     * @return Item[]
     */
    public static function deserializeList(string $contents) : array{
        return array_map(self::deserializeItemJson(...), json_decode($contents, true));
    }

    private static function serializeItemJson(Item $item) : array{
        $tag = self::serializeItemTag($item);
        $json = [
            SavedItemStackData::TAG_COUNT => $tag->getByte(SavedItemStackData::TAG_COUNT),
            SavedItemData::TAG_NAME => $tag->getString(SavedItemData::TAG_NAME),
            SavedItemData::TAG_DAMAGE => $tag->getShort(SavedItemData::TAG_DAMAGE)
        ];

        $block = $tag->getCompoundTag(SavedItemData::TAG_BLOCK);
        if($block !== null){
            $json[SavedItemData::TAG_BLOCK] = NbtSerializer::toSNBT($block);
        }

        $namedTag = $tag->getCompoundTag(SavedItemData::TAG_TAG);
        if($namedTag !== null){
            $json[SavedItemData::TAG_TAG] = NbtSerializer::toSNBT($namedTag);
        }
        return $json;
    }

    private static function deserializeItemJson(array $json) : Item{
        $tag = new CompoundTag();
        $tag->setByte(SavedItemStackData::TAG_COUNT, $json[SavedItemStackData::TAG_COUNT]);
        $tag->setString(SavedItemData::TAG_NAME, $json[SavedItemData::TAG_NAME]);
        $tag->setShort(SavedItemData::TAG_DAMAGE, $json[SavedItemData::TAG_DAMAGE]);

        if(isset($json[SavedItemData::TAG_BLOCK])){
            $tag->setTag(SavedItemData::TAG_BLOCK, NbtSerializer::fromSNBT($json[SavedItemData::TAG_BLOCK]));
        }
        if(isset($json[SavedItemData::TAG_TAG])){
            $tag->setTag(SavedItemData::TAG_TAG, NbtSerializer::fromSNBT($json[SavedItemData::TAG_TAG]));
        }

        return self::deserializeItemTag($tag);
    }

}

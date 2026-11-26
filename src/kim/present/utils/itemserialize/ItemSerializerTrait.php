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

use pocketmine\data\bedrock\item\SavedItemData;
use pocketmine\data\bedrock\item\SavedItemStackData;
use pocketmine\data\SavedDataLoadingException;
use pocketmine\item\Item;
use pocketmine\nbt\NBT;
use pocketmine\nbt\NbtException;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\Tag;
use pocketmine\VersionInfo;

use function array_keys;
use function get_debug_type;
use function is_int;
use function ksort;
use function sort;

use const SORT_STRING;

/**
 * Implements {@link ItemSerializer} on top of two primitives, the conversion between a NBT tag and the contents.
 *
 * Shapes of the tag given to {@link serializeTag()} (and expected back from {@link deserializeTag()}):
 * - single item: the item {@link CompoundTag}
 * - item list / contents: a {@link ListTag} of item compound tags;
 *   for contents each compound also has an {@link IntTag} {@link SavedItemStackData::TAG_SLOT}
 */
trait ItemSerializerTrait{

    /** Serialize the nbt tag to the contents */
    abstract protected static function serializeTag(Tag $tag) : string;

    /**
     * Deserialize the nbt tag from the contents
     *
     * @throws NbtException|SavedDataLoadingException|\InvalidArgumentException
     */
    abstract protected static function deserializeTag(string $contents) : Tag;

    public static function serialize(Item $item) : string{
        return static::serializeTag(static::serializeItemTag($item));
    }

    /** @param iterable<Item> $items */
    public static function serializeList(iterable $items) : string{
        $listTag = new ListTag([], NBT::TAG_Compound);
        foreach($items as $item){
            $listTag->push(static::serializeItemTag($item));
        }
        return static::serializeTag($listTag);
    }

    /** @param array<int, Item> $contents */
    public static function serializeContents(array $contents) : string{
        ksort($contents);
        $listTag = new ListTag([], NBT::TAG_Compound);
        foreach($contents as $slot => $item){
            if(!is_int($slot) || $slot < 0){
                throw new \InvalidArgumentException("Slot index must be a non-negative integer, got " . $slot);
            }
            if($item->isNull()){
                continue;
            }
            $listTag->push(static::serializeItemTag($item)->setInt(SavedItemStackData::TAG_SLOT, $slot));
        }
        return static::serializeTag($listTag);
    }

    public static function deserialize(string $contents) : Item{
        return static::deserializeItemTag(self::decodeTag($contents));
    }

    /** @return list<Item> */
    public static function deserializeList(string $contents) : array{
        $items = [];
        foreach(self::decodeListTag($contents) as $tag){
            $items[] = static::deserializeItemTag($tag);
        }
        return $items;
    }

    /** @return array<int, Item> */
    public static function deserializeContents(string $contents) : array{
        $items = [];
        foreach(self::decodeListTag($contents) as $tag){
            if(!($tag instanceof CompoundTag)){
                throw new SavedDataLoadingException("Expected CompoundTag item, got " . get_debug_type($tag));
            }
            $slotTag = $tag->getTag(SavedItemStackData::TAG_SLOT);
            if(!($slotTag instanceof IntTag) || $slotTag->getValue() < 0){
                throw new SavedDataLoadingException("Item is missing a valid " . SavedItemStackData::TAG_SLOT . " index");
            }
            $slot = $slotTag->getValue();
            if(isset($items[$slot])){
                throw new SavedDataLoadingException("Duplicate slot index $slot");
            }
            $tag = clone $tag;
            $tag->removeTag(SavedItemStackData::TAG_SLOT);
            $items[$slot] = static::deserializeItemTag($tag);
        }
        ksort($items);
        return $items;
    }

    /**
     * Serialize the item to the compound tag, without the world data version (of the item and of its block)
     * and with keys sorted recursively so that equal items always produce identical output.
     */
    protected static function serializeItemTag(Item $item) : CompoundTag{
        $tag = $item->nbtSerialize();
        $tag->removeTag(VersionInfo::TAG_WORLD_DATA_VERSION);
        $tag->getCompoundTag(SavedItemData::TAG_BLOCK)?->removeTag(VersionInfo::TAG_WORLD_DATA_VERSION);
        $canonical = self::canonicalizeTag($tag);
        if(!($canonical instanceof CompoundTag)){
            throw new \AssertionError("Canonical form of a CompoundTag must be a CompoundTag");
        }
        return $canonical;
    }

    /**
     * Deserialize the item from the compound tag
     *
     * @throws SavedDataLoadingException
     */
    protected static function deserializeItemTag(Tag $tag) : Item{
        if(!($tag instanceof CompoundTag)){
            throw new SavedDataLoadingException("Expected CompoundTag item, got " . get_debug_type($tag));
        }
        try{
            return Item::nbtDeserialize($tag);
        }catch(NbtException | \InvalidArgumentException $e){
            throw new SavedDataLoadingException($e->getMessage(), 0, $e);
        }
    }

    /**
     * Returns a copy of the tag with every compound's keys sorted (byte order). List order is preserved.
     */
    protected static function canonicalizeTag(Tag $tag) : Tag{
        if($tag instanceof CompoundTag){
            $names = array_keys($tag->getValue());
            sort($names, SORT_STRING);
            $result = new CompoundTag();
            foreach($names as $name){
                $result->setTag((string) $name, self::canonicalizeTag($tag->getTag((string) $name)));
            }
            return $result;
        }
        if($tag instanceof ListTag){
            $values = [];
            foreach($tag as $value){
                $values[] = self::canonicalizeTag($value);
            }
            return new ListTag($values, $tag->getTagType());
        }
        return $tag;
    }

    /** @throws SavedDataLoadingException */
    private static function decodeTag(string $contents) : Tag{
        try{
            return static::deserializeTag($contents);
        }catch(NbtException | \InvalidArgumentException $e){
            throw new SavedDataLoadingException($e->getMessage(), 0, $e);
        }
    }

    /** @throws SavedDataLoadingException */
    private static function decodeListTag(string $contents) : ListTag{
        $tag = self::decodeTag($contents);
        if(!($tag instanceof ListTag)){
            throw new SavedDataLoadingException("Expected ListTag, got " . get_debug_type($tag));
        }
        return $tag;
    }

}

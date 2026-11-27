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
use pocketmine\item\Item;
use pocketmine\nbt\NBT;
use pocketmine\nbt\NbtException;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\Tag;
use pocketmine\VersionInfo;

use function array_key_first;
use function count;
use function get_debug_type;
use function hash;
use function is_int;
use function ksort;
use function strcmp;

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

    /**
     * Deserialized items by cache key, least recently used first. Static properties of a trait are per using class,
     * so every format has its own cache.
     *
     * @var array<string, Item>
     */
    private static array $itemCache = [];
    private static int $itemCacheCapacity = 0;

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
        if(self::$itemCacheCapacity === 0){
            return static::deserializeItemTag(self::decodeTag($contents));
        }
        $key = "c" . hash("xxh128", $contents, true);
        return self::getCachedItem($key)
            ?? self::cacheItem($key, static::deserializeItemTag(self::decodeTag($contents)));
    }

    /** @return list<Item> */
    public static function deserializeList(string $contents) : array{
        $items = [];
        foreach(self::decodeListTag($contents) as $tag){
            $items[] = self::deserializeCachedItemTag($tag);
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
            // the decoded tree is owned by this call, so it can be modified without a copy
            $tag->removeTag(SavedItemStackData::TAG_SLOT);
            $items[$slot] = self::deserializeCachedItemTag($tag);
        }
        ksort($items);
        return $items;
    }

    public static function enableCache(int $capacity = 1024) : void{
        if($capacity < 1){
            throw new \InvalidArgumentException("Cache capacity must be at least 1, got $capacity");
        }
        self::$itemCacheCapacity = $capacity;
        while(count(self::$itemCache) > $capacity){
            unset(self::$itemCache[array_key_first(self::$itemCache)]);
        }
    }

    public static function disableCache() : void{
        self::$itemCacheCapacity = 0;
        self::$itemCache = [];
    }

    public static function clearCache() : void{
        self::$itemCache = [];
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
     * Returns the tag with every compound's keys sorted (byte order) at every depth. List order is preserved.
     *
     * Subtrees that are already canonical are returned as they are instead of being copied, so the result can share
     * tags with the input (including the item's own NBT): it must be treated as read-only, except for a top-level
     * compound that was not canonical (it is then a new tag).
     */
    protected static function canonicalizeTag(Tag $tag) : Tag{
        if($tag instanceof CompoundTag){
            $values = $tag->getValue();
            $sorted = true;
            $changed = false;
            $previous = null;
            foreach($values as $name => $value){
                $name = (string) $name;
                if($previous !== null && strcmp($previous, $name) > 0){
                    $sorted = false;
                }
                $previous = $name;
                $canonical = self::canonicalizeTag($value);
                if($canonical !== $value){
                    $values[$name] = $canonical;
                    $changed = true;
                }
            }
            if($sorted && !$changed){
                return $tag;
            }
            if(!$sorted){
                ksort($values, SORT_STRING);
            }
            $result = new CompoundTag();
            foreach($values as $name => $value){
                $result->setTag((string) $name, $value);
            }
            return $result;
        }
        if($tag instanceof ListTag){
            $type = $tag->getTagType();
            if($type !== NBT::TAG_Compound && $type !== NBT::TAG_List){
                return $tag; // scalar elements have nothing to sort
            }
            $values = [];
            $changed = false;
            foreach($tag as $value){
                $canonical = self::canonicalizeTag($value);
                $changed = $changed || $canonical !== $value;
                $values[] = $canonical;
            }
            return $changed ? new ListTag($values, $type) : $tag;
        }
        return $tag;
    }

    /** @throws SavedDataLoadingException */
    private static function deserializeCachedItemTag(Tag $tag) : Item{
        if(self::$itemCacheCapacity === 0){
            return static::deserializeItemTag($tag);
        }
        $key = "t" . NbtSerializer::hash($tag);
        return self::getCachedItem($key) ?? self::cacheItem($key, static::deserializeItemTag($tag));
    }

    /** Returns a copy of the cached item and marks it as most recently used */
    private static function getCachedItem(string $key) : ?Item{
        $item = self::$itemCache[$key] ?? null;
        if($item === null){
            return null;
        }
        unset(self::$itemCache[$key]);
        self::$itemCache[$key] = $item;
        return clone $item;
    }

    /** Stores a copy of the item, so later changes to the returned item do not leak into the cache */
    private static function cacheItem(string $key, Item $item) : Item{
        self::$itemCache[$key] = clone $item;
        if(count(self::$itemCache) > self::$itemCacheCapacity){
            unset(self::$itemCache[array_key_first(self::$itemCache)]);
        }
        return $item;
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

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

use pocketmine\item\Item;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\Tag;
use pocketmine\VersionInfo;

use function get_class;

trait ItemSerializerTrait{

    /**
     * Serialize the nbt tag to the contents
     *
     * @param Tag $tag
     *
     * @return string
     */
    private static function serializeTag(Tag $tag) : string{
        throw new \Error("This method should be overridden");
    }

    /**
     * Deserialize the nbt tag from the contents
     *
     * @param string $contents
     *
     * @return Tag
     */
    private static function deserializeTag(string $contents) : Tag{
        throw new \Error("This method should be overridden");
    }

    /** Serialize the item to the contents */
    public static function serialize(Item $item) : string{
        return self::encodeToUTF8(static::serializeTag(static::serializeItemTag($item)));
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
        $listTag = new ListTag();
        foreach($items as $item){
            $listTag->push(self::serializeItemTag($item));
        }
        return self::encodeToUTF8(self::serializeTag($listTag));
    }

    /** Deserialize the item from the contents */
    public static function deserialize(string $contents) : Item{
        return self::deserializeItemTag(self::deserializeTag($contents));
    }

    /**
     * Deserialize the item list from the contents
     *
     * @param string $contents
     *
     * @return Item[]
     */
    public static function deserializeList(string $contents) : array{
        $listTag = self::deserializeTag($contents);
        if(!($listTag instanceof ListTag)){
            throw new \InvalidArgumentException("Invalid tag type : " . get_class($listTag));
        }

        /** @var Item[] $items */
        $items = [];
        foreach($listTag as $tag){
            $items[] = self::deserializeItemTag($tag);
        }
        return $items;
    }

    /** Convert the contents to UTF-8 encoding */
    protected static function encodeToUTF8(string $contents) : string{
        return mb_convert_encoding($contents, "UTF-8", mb_detect_encoding($contents));
    }

    /** Serialize the item to the compound tag */
    protected static function serializeItemTag(Item $item) : CompoundTag{
        $tag = $item->nbtSerialize();
        $tag->removeTag(VersionInfo::TAG_WORLD_DATA_VERSION);
        return $tag;
    }

    /** Deserialize the item from the compound tag */
    protected static function deserializeItemTag(Tag $tag) : Item{
        if(!($tag instanceof CompoundTag)){
            throw new \InvalidArgumentException("Invalid tag type : " . get_class($tag));
        }

        return Item::nbtDeserialize($tag);
    }

}

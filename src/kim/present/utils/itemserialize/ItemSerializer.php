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

use pocketmine\data\SavedDataLoadingException;
use pocketmine\item\Item;

/**
 * Serializes items to strings and back.
 *
 * Every serialize method produces deterministic output: the same item always becomes the same string,
 * regardless of the order its NBT keys were stored in, so the result can be compared, hashed or used as a key.
 *
 * Every deserialize method throws {@link SavedDataLoadingException} for any malformed or incompatible input.
 */
interface ItemSerializer{

    /** Serialize the item to the contents */
    public static function serialize(Item $item) : string;

    /**
     * Serialize the item list to the contents. Keys are discarded, use {@link serializeContents()} to keep them.
     *
     * @param iterable<Item> $items
     */
    public static function serializeList(iterable $items) : string;

    /**
     * Serialize slot-indexed items (e.g. {@link \pocketmine\inventory\Inventory::getContents()}) to the contents.
     * Slot indices are preserved, empty (air) slots are skipped.
     *
     * @param array<int, Item> $contents slot index => item
     *
     * @throws \InvalidArgumentException if a slot index is negative
     */
    public static function serializeContents(array $contents) : string;

    /**
     * Deserialize the item from the contents
     *
     * @throws SavedDataLoadingException
     */
    public static function deserialize(string $contents) : Item;

    /**
     * Deserialize the item list from the contents
     *
     * @return list<Item>
     *
     * @throws SavedDataLoadingException
     */
    public static function deserializeList(string $contents) : array;

    /**
     * Deserialize slot-indexed items from the contents produced by {@link serializeContents()}.
     * The result can be passed directly to {@link \pocketmine\inventory\Inventory::setContents()}.
     *
     * @return array<int, Item> slot index => item, sorted by slot index
     *
     * @throws SavedDataLoadingException
     */
    public static function deserializeContents(string $contents) : array;

    /**
     * Enable the deserialization cache of this format (off by default).
     *
     * Deserialized items are kept (least recently used evicted beyond $capacity) and copies are returned when the
     * same contents are deserialized again, skipping the item data upgrade and item construction.
     * {@link deserialize()} is keyed by the contents string, list and contents entries by the hash of each item tag.
     * Every call returns a new copy, so the returned items can be modified freely.
     *
     * @throws \InvalidArgumentException if the capacity is less than 1
     */
    public static function enableCache(int $capacity = 1024) : void;

    /** Disable and clear the deserialization cache of this format */
    public static function disableCache() : void;

    /** Clear the deserialization cache of this format (e.g. after registering custom items) */
    public static function clearCache() : void;

}

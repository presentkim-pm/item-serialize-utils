<!-- PROJECT BADGES -->
<div align="center">

[![Packagist][packagist-badge]][packagist-url]
[![CI][ci-badge]][ci-url]
[![Stars][stars-badge]][stars-url]
[![License][license-badge]][license-url]

</div>


<!-- PROJECT LOGO -->
<br />
<div align="center">
  <img src="https://raw.githubusercontent.com/presentkim-pm/item-serialize-utils/main/assets/icon.png" alt="Logo" width="80" height="80"/>
  <h3>item-serialize-utils</h3>
  <p align="center">
    Save and load PocketMine-MP items and whole inventories in one line.

[Packagist][packagist-url] · [Changelog](CHANGELOG.md) · [Report a bug][issues-url] · [Request a feature][issues-url]

  </p>
</div>

```php
$data = Base64ItemSerializer::serializeContents($player->getInventory()->getContents()); // → string
$player->getInventory()->setContents(Base64ItemSerializer::deserializeContents($data));  // ← string
```

-----

## 🌟 Why item-serialize-utils?

PocketMine-MP 5 no longer `json_encode()`s items, so every plugin that stores items ends up writing the same code:
an NBT serializer, `TreeRoot`, base64, error handling for each layer, and its own format for slot indices.
This library does it once, correctly:

- **One line per job** : single items, lists, and whole inventories with their slot indices
- **Five formats, one API** : Binary, Base64, Hex, SNBT (readable and editable) and JSON
- **Stable output** : the same item always becomes the same string, even after a server update
  (NBT keys are sorted, the server's data version is stripped), so strings can be compared, hashed or used as keys
- **One exception to catch** : any bad or tampered input throws `SavedDataLoadingException`, never a warning or `TypeError`
- **Fast** : optional cache that loads repeated contents 10~20× faster
- **Compact** : about 15% shorter than encoding `Item::nbtSerialize()` yourself
- **Tested** : run against the real server item code on PHP 8.4 / 8.5

<details>
<summary>What you would write without it</summary>

```php
$serializer = new BigEndianNbtSerializer();

// save
$data = base64_encode($serializer->write(new TreeRoot($item->nbtSerialize())));

// load
$binary = base64_decode($data, true);
if($binary === false){ /* ... */ }
try{
    $item = Item::nbtDeserialize($serializer->read($binary)->mustGetCompoundTag());
}catch(NbtDataException | UnexpectedTagTypeException | SavedDataLoadingException $e){ /* ... */ }

// ...and again for lists, for inventories with slot indices, and for every other format
```

</details>

## 🎯 Use cases

| You are building...                          | Use                                          |
|----------------------------------------------|----------------------------------------------|
| Backpacks, vaults, ender chests, mailboxes   | `serializeContents()` into a database        |
| Kits, rewards, shop items in `config.yml`    | `SnbtItemSerializer` (admins can edit it)    |
| Auction houses, trade logs                   | `serialize()` per item, stable for diffs     |
| Inventory sync between servers               | `serializeContents()` over your transport   |
| Duplicate detection, item counting           | `serialize()` as an array key                |
| Shop / menu GUIs loading the same items      | `enableCache()`                              |

-----

## 📦 Installation

```sh
composer require presentkim/item-serialize-utils
```

| Requirement | Version                                                                     |
|-------------|-----------------------------------------------------------------------------|
| Server      | [axolotl-pm/PocketMine-MP][axolotl-url] (fork of PocketMine-MP 5)           |
| PHP         | **8.4** or newer                                                            |
| NBT         | [presentkim/nbt-serializer][nbt-serializer-url] `^3.0` (installed with it) |

> The Poggit virion for the original PocketMine-MP 5.44 and older is kept on the
> [`legacy/pmmp-5.44`][legacy-url] branch (no longer maintained).

-----

## 🧩 Choosing a format

Every class is in `kim\present\utils\itemserialize` and has exactly the same methods.
Sizes are for a diamond sword named "Excalibur" with one lore line and Sharpness V.

| Class                  | Size | Best for                                                  |
|------------------------|-----:|-----------------------------------------------------------|
| `BinaryItemSerializer` | 143 B | Database `BLOB` columns, files. Raw bytes, not text       |
| `Base64ItemSerializer` | 192 B | Database text columns, YAML/JSON configs, network packets |
| `HexItemSerializer`    | 286 B | Places where base64 characters are not allowed            |
| `SnbtItemSerializer`   | 125 B | Configs humans read and edit, logs, commands              |
| `JsonItemSerializer`   | 137 B | Web APIs and JSON documents                               |

```text
SNBT  {Count:1b,Damage:0s,Name:"minecraft:diamond_sword",tag:{display:{Lore:["Legendary"],Name:"Excalibur"},ench:[{id:9s,lvl:5s}]}}
JSON  {"Count":1,"Name":"minecraft:diamond_sword","Damage":0,"tag":"{display:{Lore:[\"Legendary\"],Name:\"Excalibur\"},ench:[{id:9s,lvl:5s}]}"}
```

> `JsonItemSerializer` is its own format, not compatible with `Item::legacyJsonDeserialize()`.

-----

## 🚀 Quick start

```php
use kim\present\utils\itemserialize\Base64ItemSerializer;

// One item
$data = Base64ItemSerializer::serialize($item);
$item = Base64ItemSerializer::deserialize($data);

// A list of items (keys are discarded)
$data  = Base64ItemSerializer::serializeList([$sword, $apple]);
$items = Base64ItemSerializer::deserializeList($data);        // list<Item>

// A whole inventory (slot indices kept, empty slots skipped)
$data     = Base64ItemSerializer::serializeContents($inventory->getContents());
$contents = Base64ItemSerializer::deserializeContents($data); // array<int, Item>
$inventory->setContents($contents);
```

### Handling bad input

Stored data can be edited by hand, truncated, or written by an older server. Catch one exception:

```php
use pocketmine\data\SavedDataLoadingException;

try{
    $item = SnbtItemSerializer::deserialize($input);
}catch(SavedDataLoadingException $e){
    // malformed text, wrong shape (a list given to deserialize()), unknown item, out of range values, ...
    $this->getLogger()->warning("Invalid item data: " . $e->getMessage());
}
```

-----

## 📚 Examples

<details open>
<summary><b>Player vault in a database</b></summary>

```php
// save (e.g. on InventoryCloseEvent)
$data = BinaryItemSerializer::serializeContents($vault->getContents());
$stmt = $db->prepare("REPLACE INTO vaults (uuid, contents) VALUES (:uuid, :contents)");
$stmt->bindValue(":uuid", $player->getUniqueId()->toString());
$stmt->bindValue(":contents", $data, SQLITE3_BLOB); // binary data needs a BLOB column
$stmt->execute();

// load
$vault->setContents(BinaryItemSerializer::deserializeContents($row["contents"]));
```

</details>

<details>
<summary><b>Kits in config.yml</b></summary>

```yaml
kits:
  starter:
    - '{Count:1b,Damage:0s,Name:"minecraft:stone_sword"}'
    - '{Count:16b,Damage:0s,Name:"minecraft:bread"}'
```

```php
foreach($this->getConfig()->getNested("kits.starter") as $snbt){
    $player->getInventory()->addItem(SnbtItemSerializer::deserialize($snbt));
}

// "/kit save starter" : turn the player's inventory into editable config entries
$entries = array_map(SnbtItemSerializer::serialize(...), array_values($player->getInventory()->getContents()));
```

</details>

<details>
<summary><b>Shop GUI with the cache</b></summary>

```php
// once, e.g. in onEnable()
Base64ItemSerializer::enableCache(); // up to 1024 items, least recently used are evicted

// every time the menu opens: repeated contents skip the server's item loading
foreach($shopEntries as $slot => $entry){
    $menu->setItem($slot, Base64ItemSerializer::deserialize($entry["item"]));
}
```

Each call returns a fresh copy, so changing the returned item (count, lore, ...) never affects the cache.

</details>

<details>
<summary><b>Detecting identical items</b></summary>

```php
// Output is deterministic, so the string itself is a key
$counts = [];
foreach($inventory->getContents() as $item){
    $key = SnbtItemSerializer::serialize((clone $item)->setCount(1));
    $counts[$key] = ($counts[$key] ?? 0) + $item->getCount();
}
```

</details>

-----

## 📖 API reference

| Method                                                     | Description                                                                 |
|------------------------------------------------------------|-----------------------------------------------------------------------------|
| `serialize(Item $item) : string`                           | Serialize one item                                                          |
| `deserialize(string $contents) : Item`                     | Deserialize one item                                                        |
| `serializeList(iterable<Item> $items) : string`            | Serialize items in order; keys are discarded                                |
| `deserializeList(string $contents) : list<Item>`           | Deserialize items in order                                                  |
| `serializeContents(array<int, Item> $contents) : string`   | Serialize slot → item, skipping air. Negative slots throw `InvalidArgumentException` |
| `deserializeContents(string $contents) : array<int, Item>` | Deserialize slot → item, sorted by slot, ready for `Inventory::setContents()` |
| `enableCache(int $capacity = 1024) : void`                 | Turn on this format's deserialization cache (off by default)               |
| `disableCache() : void`                                    | Turn off and clear the cache                                                |
| `clearCache() : void`                                      | Clear the cache, e.g. after item registrations change at runtime            |

All methods are static. Every `deserialize*()` method throws `pocketmine\data\SavedDataLoadingException` for invalid input.

### About the cache

Most of the time spent loading an item goes to the server's item data upgrade and item construction, not the NBT
decoding. With the cache on, loading the same contents again costs about 0.5 µs for a plain item and 3 µs for an
enchanted, named sword (10~30 µs without it), and a 36-slot inventory loads about 2.4× faster.

- Each format class has its own cache: enabling it on `Base64ItemSerializer` does not affect the others.
- `deserialize()` is keyed by the input string, `deserializeList()` / `deserializeContents()` entries by the hash of
  each item. Invalid input is never cached.
- It helps when the same data is loaded repeatedly (GUIs, kits). For per-player data, leave it off.

### Output details

- The server's data version tag (`PMMPDataVersion`) is removed from the item and its `Block` tag.
- Compound keys are sorted at every depth; list order is kept.
- Contents are a list of items, each with an extra `Slot` **int** tag, so any slot index works
  (the vanilla `Slot` byte stops at 255). In JSON, each item object gets a `Slot` number.
- Data written by 3.x of this library still loads.

-----

## 🛠️ Custom formats

Implement two methods and get the whole API (lists, contents, cache, error handling):

```php
use kim\present\serializer\nbt\NbtSerializer;
use kim\present\utils\itemserialize\ItemSerializer;
use kim\present\utils\itemserialize\ItemSerializerTrait;
use pocketmine\nbt\tag\Tag;

final class GzipItemSerializer implements ItemSerializer{
    use ItemSerializerTrait;

    protected static function serializeTag(Tag $tag) : string{
        return NbtSerializer::toCompressed($tag);
    }

    protected static function deserializeTag(string $contents) : Tag{
        return NbtSerializer::fromCompressed($contents);
    }
}
```

-----

## 🧪 Development

```sh
composer install --ignore-platform-req='ext-*'
composer test
```

The tests use the real `axolotl-pm/pocketmine-mp` item code without its native extensions:
`tests/polyfill` provides the few `ext-encoding` / `ext-morton` functions that item (de)serialization needs,
and is skipped when the real extensions are loaded.

-----

## 📄 License

Distributed under the **MIT** License. See [LICENSE][license-url] for more information.


[packagist-badge]: https://img.shields.io/packagist/v/presentkim/item-serialize-utils.svg?style=for-the-badge

[packagist-url]: https://packagist.org/packages/presentkim/item-serialize-utils

[ci-badge]: https://img.shields.io/github/actions/workflow/status/presentkim-pm/item-serialize-utils/ci.yml?branch=main&style=for-the-badge

[ci-url]: https://github.com/presentkim-pm/item-serialize-utils/actions/workflows/ci.yml

[stars-badge]: https://img.shields.io/github/stars/presentkim-pm/item-serialize-utils.svg?style=for-the-badge

[license-badge]: https://img.shields.io/github/license/presentkim-pm/item-serialize-utils.svg?style=for-the-badge

[stars-url]: https://github.com/presentkim-pm/item-serialize-utils/stargazers

[issues-url]: https://github.com/presentkim-pm/item-serialize-utils/issues

[license-url]: https://github.com/presentkim-pm/item-serialize-utils/blob/main/LICENSE

[legacy-url]: https://github.com/presentkim-pm/item-serialize-utils/tree/legacy/pmmp-5.44

[nbt-serializer-url]: https://github.com/presentkim-pm/nbt-serializer

[axolotl-url]: https://github.com/axolotl-pm/PocketMine-MP

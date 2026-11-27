# Changelog

## 4.0.0 (2026-11-27)

The Poggit virion version for the original [pmmp/PocketMine-MP](https://github.com/pmmp/PocketMine-MP) 5.x is kept on the
[`legacy/pmmp-5.44`](https://github.com/presentkim-pm/item-serialize-utils/tree/legacy/pmmp-5.44) branch.

### ⚠️ Breaking changes
- Targets [axolotl-pm/PocketMine-MP](https://github.com/axolotl-pm/PocketMine-MP) and **PHP 8.4+**.
  `presentkim/nbt-serializer ^3.0` is now an explicit Composer dependency (it was an implicit virion before).
- Distributed with Composer: the Poggit virion files (`.poggit.yml`, `virion.yml`) are removed.
- `ItemSerializer` has new methods: `serializeContents()`, `deserializeContents()`, `enableCache()`, `disableCache()`
  and `clearCache()`. Custom implementations get them from `ItemSerializerTrait`.
- `ItemSerializerTrait`: `serializeTag()` / `deserializeTag()` are now abstract `protected static` methods
  and `encodeToUTF8()` is removed.
- Every `deserialize*()` method throws `pocketmine\data\SavedDataLoadingException` for invalid input
  (instead of `InvalidArgumentException`, `NbtDataException`, `TypeError`, ...).
- Serialized output differs from 3.x (data written by 3.x still loads):
  - compound keys are sorted at every depth
  - `PMMPDataVersion` is also removed from the `Block` tag of block items
  - SNBT (and the SNBT parts of JSON) follows nbt-serializer 3.0: unicode as-is, floats without precision loss

### ✨ Features
- `serializeContents()` / `deserializeContents()`: slot-indexed items such as `Inventory::getContents()`,
  with any slot index and empty slots skipped. The result can be passed to `Inventory::setContents()`.
- Deterministic output: the same item always becomes the same string, whatever order its NBT keys were stored in.
- Optional per-format deserialization cache: `enableCache(int $capacity = 1024)`, `disableCache()`, `clearCache()`.
  Least recently used items are evicted and every call returns a copy.

### 🐛 Fixes
- `BinaryItemSerializer` ran its output through `mb_convert_encoding()`, which replaced bytes that are not valid UTF-8
  and corrupted the data (for example items with a lore line of 128 bytes or more).
- `ItemSerializerTrait` called the format methods with `self::`, so formats built on the trait could not override them.
- `JsonItemSerializer` dropped top-level item keys other than `Count`, `Name`, `Damage`, `Block` and `tag`;
  they are now kept as SNBT strings.
- Invalid JSON or JSON with wrong value types raised `TypeError`s; invalid hex input raised a PHP warning
  (an exception under PocketMine-MP's error handler).

### ⚡ Performance
- Canonicalization reuses already sorted subtrees: serialization of large NBT is about 32~36% faster.
- `deserializeContents()` no longer deep-copies the decoded tags: about 16% faster for a 36-slot inventory.
- With the cache enabled, deserializing the same contents again takes about 0.5 µs for a plain item and 3 µs
  for an enchanted, named sword (10~30 µs without the cache).

### 🧪 Development
- PHPUnit tests (73 tests) against the real `axolotl-pm/pocketmine-mp` item code, and GitHub Actions CI on PHP 8.4 / 8.5.

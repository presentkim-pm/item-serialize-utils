<?php

declare(strict_types=1);

namespace kim\present\utils\itemserialize\tests;

use kim\present\serializer\nbt\NbtSerializer;
use kim\present\utils\itemserialize\Base64ItemSerializer;
use kim\present\utils\itemserialize\BinaryItemSerializer;
use kim\present\utils\itemserialize\HexItemSerializer;
use kim\present\utils\itemserialize\ItemSerializer;
use kim\present\utils\itemserialize\ItemSerializerTrait;
use kim\present\utils\itemserialize\JsonItemSerializer;
use kim\present\utils\itemserialize\SnbtItemSerializer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use pocketmine\block\VanillaBlocks;
use pocketmine\data\SavedDataLoadingException;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\Tag;
use pocketmine\VersionInfo;

use function str_repeat;

final class ItemSerializerTest extends TestCase{

    /** @return array<string, array{class-string<ItemSerializer>}> */
    public static function serializers() : array{
        return [
            'binary' => [BinaryItemSerializer::class],
            'base64' => [Base64ItemSerializer::class],
            'hex' => [HexItemSerializer::class],
            'snbt' => [SnbtItemSerializer::class],
            'json' => [JsonItemSerializer::class],
        ];
    }

    private static function sword() : Item{
        $item = VanillaItems::DIAMOND_SWORD()->setCustomName("§a한글 이름")->setLore(["첫 줄", "second"]);
        $item->addEnchantment(new EnchantmentInstance(VanillaEnchantments::SHARPNESS(), 3));
        $item->setCanPlaceOn(["minecraft:stone"]);
        $item->setCanDestroy(["minecraft:dirt", "minecraft:grass_block"]);
        return $item;
    }

    /** @return list<Item> */
    private static function sampleItems() : array{
        return [
            self::sword(),
            VanillaBlocks::OAK_LOG()->asItem()->setCount(12),
            VanillaItems::APPLE()->setCount(64),
            // Lore >= 128 bytes puts 0x80+ bytes (invalid UTF-8 on their own) in the binary output
            VanillaItems::PAPER()->setLore([str_repeat("x", 200)]),
        ];
    }

    private static function assertSameItem(Item $expected, Item $actual) : void{
        self::assertTrue($expected->equalsExact($actual), "Item mismatch:\n$expected\n$actual");
    }

    /** @param class-string<ItemSerializer> $serializer */
    #[DataProvider('serializers')]
    public function testSingleRoundTrip(string $serializer) : void{
        foreach(self::sampleItems() as $item){
            $restored = $serializer::deserialize($serializer::serialize($item));
            self::assertSameItem($item, $restored);
            self::assertSame($serializer::serialize($item), $serializer::serialize($restored));
        }
    }

    /** @param class-string<ItemSerializer> $serializer */
    #[DataProvider('serializers')]
    public function testListRoundTrip(string $serializer) : void{
        $items = self::sampleItems();
        $restored = $serializer::deserializeList($serializer::serializeList($items));
        self::assertCount(count($items), $restored);
        self::assertSame(array_keys($items), array_keys($restored));
        foreach($items as $i => $item){
            self::assertSameItem($item, $restored[$i]);
        }

        self::assertSame([], $serializer::deserializeList($serializer::serializeList([])));
        self::assertCount(2, $serializer::deserializeList($serializer::serializeList(new \ArrayIterator([
            VanillaItems::APPLE(), VanillaItems::STICK(),
        ]))));
    }

    /** @param class-string<ItemSerializer> $serializer */
    #[DataProvider('serializers')]
    public function testContentsKeepSlots(string $serializer) : void{
        $contents = [
            300 => VanillaItems::STICK(),
            5 => self::sword(),
            0 => VanillaItems::APPLE()->setCount(3),
            7 => VanillaItems::AIR(),
        ];
        $restored = $serializer::deserializeContents($serializer::serializeContents($contents));
        self::assertSame([0, 5, 300], array_keys($restored));
        foreach($restored as $slot => $item){
            self::assertSameItem($contents[$slot], $item);
        }

        self::assertSame([], $serializer::deserializeContents($serializer::serializeContents([])));
        // key order of the input does not change the output
        $reordered = [0 => $contents[0], 300 => $contents[300], 5 => $contents[5]];
        self::assertSame($serializer::serializeContents($contents), $serializer::serializeContents($reordered));
    }

    /** @param class-string<ItemSerializer> $serializer */
    #[DataProvider('serializers')]
    public function testNegativeSlotRejected(string $serializer) : void{
        $this->expectException(\InvalidArgumentException::class);
        $serializer::serializeContents([-1 => VanillaItems::APPLE()]);
    }

    /** @param class-string<ItemSerializer> $serializer */
    #[DataProvider('serializers')]
    public function testDeterministicRegardlessOfNbtKeyOrder(string $serializer) : void{
        $a = VanillaItems::STICK();
        $a->setNamedTag((new CompoundTag())->setString("zeta", "z")->setInt("alpha", 1)
            ->setTag("nested", (new CompoundTag())->setByte("b", 1)->setByte("a", 2)));
        $b = VanillaItems::STICK();
        $b->setNamedTag((new CompoundTag())
            ->setTag("nested", (new CompoundTag())->setByte("a", 2)->setByte("b", 1))
            ->setInt("alpha", 1)->setString("zeta", "z"));

        self::assertSame($serializer::serialize($a), $serializer::serialize($b));
        self::assertSame($serializer::serializeList([$a]), $serializer::serializeList([$b]));
    }

    public function testOutputHasNoWorldDataVersion() : void{
        self::assertStringNotContainsString(VersionInfo::TAG_WORLD_DATA_VERSION, SnbtItemSerializer::serialize(self::sword()));
        self::assertStringNotContainsString(VersionInfo::TAG_WORLD_DATA_VERSION, JsonItemSerializer::serialize(self::sword()));
        self::assertStringNotContainsString(VersionInfo::TAG_WORLD_DATA_VERSION,
            SnbtItemSerializer::serialize(VanillaBlocks::OAK_LOG()->asItem()));
    }

    /**
     * Regression: older versions ran mb_convert_encoding() over the binary output, replacing bytes that are not
     * valid UTF-8 and corrupting the data.
     */
    public function testBinaryOutputIsUnmodifiedNbt() : void{
        $item = VanillaItems::PAPER()->setLore([str_repeat("x", 200)])->setCount(64);
        $binary = BinaryItemSerializer::serialize($item);
        self::assertFalse(mb_check_encoding($binary, 'UTF-8'), "sample must contain non UTF-8 bytes");
        self::assertSame(NbtSerializer::toBinary(NbtSerializer::fromBinary($binary)), $binary);
        self::assertSame(bin2hex($binary), HexItemSerializer::serialize($item));
        self::assertSame(base64_encode($binary), Base64ItemSerializer::serialize($item));
        self::assertSameItem($item, BinaryItemSerializer::deserialize($binary));
    }

    /** @return array<string, array{class-string<ItemSerializer>, string}> */
    public static function invalidInputs() : array{
        return [
            'binary garbage' => [BinaryItemSerializer::class, "\x0a\x00"],
            'base64 garbage' => [Base64ItemSerializer::class, "!!not base64!!"],
            'hex garbage' => [HexItemSerializer::class, "zz"],
            'snbt garbage' => [SnbtItemSerializer::class, "{Count:"],
            'snbt not compound' => [SnbtItemSerializer::class, "1b"],
            'snbt missing name' => [SnbtItemSerializer::class, "{Count:1b,Damage:0s}"],
            'json garbage' => [JsonItemSerializer::class, "{"],
            'json scalar' => [JsonItemSerializer::class, "1"],
            'json missing count' => [JsonItemSerializer::class, '{"Name":"minecraft:apple","Damage":0}'],
            'json wrong type' => [JsonItemSerializer::class, '{"Count":"1","Name":"minecraft:apple","Damage":0}'],
            'json bad snbt' => [JsonItemSerializer::class, '{"Count":1,"Name":"minecraft:apple","Damage":0,"tag":"{"}'],
            'json count overflow' => [JsonItemSerializer::class, '{"Count":1000,"Name":"minecraft:apple","Damage":0}'],
            'unknown item' => [SnbtItemSerializer::class, '{Count:1b,Name:"minecraft:not_an_item",Damage:0s}'],
        ];
    }

    /** @param class-string<ItemSerializer> $serializer */
    #[DataProvider('invalidInputs')]
    public function testInvalidInputThrowsSavedDataLoadingException(string $serializer, string $contents) : void{
        $this->expectException(SavedDataLoadingException::class);
        $serializer::deserialize($contents);
    }

    /** @param class-string<ItemSerializer> $serializer */
    #[DataProvider('serializers')]
    public function testShapeMismatchThrows(string $serializer) : void{
        try{
            $serializer::deserializeList($serializer::serialize(VanillaItems::APPLE()));
            self::fail("deserializeList() accepted a single item");
        }catch(SavedDataLoadingException){
        }
        try{
            $serializer::deserialize($serializer::serializeList([VanillaItems::APPLE()]));
            self::fail("deserialize() accepted a list");
        }catch(SavedDataLoadingException){
        }
        try{
            // list output has no slot indices
            $serializer::deserializeContents($serializer::serializeList([VanillaItems::APPLE()]));
            self::fail("deserializeContents() accepted a list without slots");
        }catch(SavedDataLoadingException){
        }
        $this->addToAssertionCount(3);
    }

    public function testDuplicateSlotThrows() : void{
        $this->expectException(SavedDataLoadingException::class);
        SnbtItemSerializer::deserializeContents(
            '[{Count:1b,Damage:0s,Name:"minecraft:apple",Slot:1},{Count:1b,Damage:0s,Name:"minecraft:stick",Slot:1}]'
        );
    }

    /** Output written by 3.x (before canonical key order) must still load. */
    public function testLegacyOutputStillLoads() : void{
        $item = SnbtItemSerializer::deserialize('{Count:5b,Name:"minecraft:apple",Damage:0s,tag:{display:{Name:"Old"}}}');
        self::assertSame(5, $item->getCount());
        self::assertSame("Old", $item->getCustomName());

        $item = JsonItemSerializer::deserialize('{"Count":2,"Name":"minecraft:apple","Damage":0,"tag":"{display:{Name:\"Old\"}}"}');
        self::assertSame(2, $item->getCount());
        self::assertSame("Old", $item->getCustomName());

        // nbt-serializer 2.x wrote strings with json_encode() escapes (\uXXXX, \/)
        $item = SnbtItemSerializer::deserialize(
            '{Count:1b,Name:"minecraft:apple",Damage:0s,tag:{display:{Name:"\\u00a7a\\ud55c\\uae00 a\\/b"}}}'
        );
        self::assertSame("§a한글 a/b", $item->getCustomName());
        $item = JsonItemSerializer::deserialize(
            '{"Count":1,"Name":"minecraft:apple","Damage":0,"tag":"{display:{Name:\\"\\\\ud55c\\\\uae00\\"}}"}'
        );
        self::assertSame("한글", $item->getCustomName());

        // 3.x wrote empty lists with TAG_End element type
        self::assertSame([], SnbtItemSerializer::deserializeList('[]'));
    }

    public function testJsonFormat() : void{
        $json = JsonItemSerializer::serialize(self::sword());
        self::assertStringStartsWith('{"Count":1,"Name":"minecraft:diamond_sword","Damage":0,"tag":"', $json);
        self::assertSame(["Count", "Name", "Damage", "tag"], array_keys(json_decode($json, true, flags: JSON_THROW_ON_ERROR)));

        // unknown top-level keys survive as SNBT
        $tag = JsonItemSerializer::deserializeList('[{"Count":1,"Name":"minecraft:apple","Damage":0,"Extra":"5b"}]');
        self::assertCount(1, $tag);

        $contents = json_decode(JsonItemSerializer::serializeContents([9 => VanillaItems::APPLE()]), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(9, $contents[0]["Slot"]);
    }

    /** The trait must call the using class' primitives through static::, so custom formats work. */
    public function testCustomFormatViaTrait() : void{
        $custom = new class implements ItemSerializer{
            use ItemSerializerTrait;

            protected static function serializeTag(Tag $tag) : string{
                return "custom:" . NbtSerializer::toSnbt($tag);
            }

            protected static function deserializeTag(string $contents) : Tag{
                if(!str_starts_with($contents, "custom:")){
                    throw new SavedDataLoadingException("missing prefix");
                }
                return NbtSerializer::fromSnbt(substr($contents, 7));
            }
        };
        $class = $custom::class;
        $item = self::sword();
        self::assertStringStartsWith("custom:", $class::serialize($item));
        self::assertSameItem($item, $class::deserialize($class::serialize($item)));
        self::assertSameItem($item, $class::deserializeList($class::serializeList([$item]))[0]);
        self::assertSameItem($item, $class::deserializeContents($class::serializeContents([4 => $item]))[4]);
    }
}

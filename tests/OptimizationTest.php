<?php

declare(strict_types=1);

namespace kim\present\utils\itemserialize\tests;

use kim\present\serializer\nbt\NbtSerializer;
use kim\present\utils\itemserialize\Base64ItemSerializer;
use kim\present\utils\itemserialize\BinaryItemSerializer;
use kim\present\utils\itemserialize\HexItemSerializer;
use kim\present\utils\itemserialize\ItemSerializer;
use kim\present\utils\itemserialize\JsonItemSerializer;
use kim\present\utils\itemserialize\SnbtItemSerializer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use pocketmine\block\VanillaBlocks;
use pocketmine\data\SavedDataLoadingException;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\nbt\tag\Tag;

final class OptimizationTest extends TestCase{

    /** @var list<class-string<ItemSerializer>> */
    private const SERIALIZERS = [
        BinaryItemSerializer::class, Base64ItemSerializer::class, HexItemSerializer::class,
        SnbtItemSerializer::class, JsonItemSerializer::class,
    ];

    protected function tearDown() : void{
        foreach(self::SERIALIZERS as $serializer){
            $serializer::disableCache();
        }
    }

    /** @return array<string, array{class-string<ItemSerializer>}> */
    public static function serializers() : array{
        $result = [];
        foreach(self::SERIALIZERS as $serializer){
            $result[$serializer] = [$serializer];
        }
        return $result;
    }

    /** The previous implementation: rebuilds every compound and list. Used as the reference. */
    private static function referenceCanonicalize(Tag $tag) : Tag{
        if($tag instanceof CompoundTag){
            $names = array_map('strval', array_keys($tag->getValue()));
            sort($names, SORT_STRING);
            $result = new CompoundTag();
            foreach($names as $name){
                $result->setTag($name, self::referenceCanonicalize($tag->getTag($name)));
            }
            return $result;
        }
        if($tag instanceof ListTag){
            $values = [];
            foreach($tag as $value){
                $values[] = self::referenceCanonicalize($value);
            }
            return new ListTag($values, $tag->getTagType());
        }
        return $tag;
    }

    private static function canonicalize(Tag $tag) : Tag{
        return (new \ReflectionMethod(BinaryItemSerializer::class, 'canonicalizeTag'))->invoke(null, $tag);
    }

    /** @return array<string, array{Tag}> */
    public static function tags() : array{
        $nested = (new CompoundTag())->setByte("b", 1)->setByte("a", 2);
        return [
            'sorted' => [(new CompoundTag())->setInt("a", 1)->setInt("b", 2)],
            'unsorted' => [(new CompoundTag())->setInt("b", 2)->setInt("a", 1)],
            'sorted parent, unsorted child' => [(new CompoundTag())->setInt("a", 1)->setTag("b", clone $nested)],
            'numeric keys' => [(new CompoundTag())->setInt("10", 1)->setInt("9", 2)->setInt("1", 3)],
            'list of unsorted compounds' => [(new CompoundTag())->setTag("l", new ListTag([clone $nested, clone $nested]))],
            'list of lists' => [new ListTag([new ListTag([clone $nested]), new ListTag([], NBT::TAG_Int)])],
            'scalar list' => [new ListTag([new StringTag("z"), new StringTag("a")])],
            'empty' => [new CompoundTag()],
            'scalar' => [new IntTag(5)],
        ];
    }

    #[DataProvider('tags')]
    public function testCanonicalizeMatchesReference(Tag $tag) : void{
        $before = NbtSerializer::toSnbt($tag, false);
        $canonical = self::canonicalize($tag);
        self::assertSame(
            NbtSerializer::toBinary(self::referenceCanonicalize($tag)),
            NbtSerializer::toBinary($canonical)
        );
        self::assertSame($before, NbtSerializer::toSnbt($tag, false), "input must not be modified");
    }

    public function testCanonicalTagIsReturnedAsIs() : void{
        $tag = (new CompoundTag())->setInt("a", 1)->setTag("b", (new CompoundTag())->setByte("x", 1));
        self::assertSame($tag, self::canonicalize($tag));
        $list = new ListTag([new StringTag("z"), new StringTag("a")]);
        self::assertSame($list, self::canonicalize($list));
    }

    /** @param class-string<ItemSerializer> $serializer */
    #[DataProvider('serializers')]
    public function testSerializeDoesNotModifyItem(string $serializer) : void{
        $item = VanillaItems::STICK();
        $item->setNamedTag((new CompoundTag())->setString("zeta", "z")->setInt("alpha", 1));
        $before = NbtSerializer::toSnbt($item->getNamedTag(), false);
        $serializer::serialize($item);
        $serializer::serializeContents([3 => $item]);
        self::assertSame($before, NbtSerializer::toSnbt($item->getNamedTag(), false));
    }

    /** @param class-string<ItemSerializer> $serializer */
    #[DataProvider('serializers')]
    public function testCacheReturnsIndependentCopies(string $serializer) : void{
        $sword = VanillaItems::DIAMOND_SWORD()->setCustomName("cached");
        $sword->addEnchantment(new EnchantmentInstance(VanillaEnchantments::SHARPNESS(), 2));
        $contents = $serializer::serialize($sword);
        $list = $serializer::serializeList([$sword, VanillaBlocks::OAK_LOG()->asItem()]);
        $slots = $serializer::serializeContents([2 => $sword]);

        $serializer::enableCache();
        $first = $serializer::deserialize($contents);
        $second = $serializer::deserialize($contents);
        self::assertNotSame($first, $second);
        self::assertTrue($first->equalsExact($sword));
        self::assertTrue($second->equalsExact($sword));

        // modifying a returned item must not leak into later results
        $first->setCustomName("changed")->setCount(5);
        $first->getNamedTag()->setString("leak", "x");
        $third = $serializer::deserialize($contents);
        self::assertTrue($third->equalsExact($sword));

        foreach([1, 2] as $_){
            $items = $serializer::deserializeList($list);
            self::assertTrue($items[0]->equalsExact($sword));
            self::assertTrue($items[1]->equalsExact(VanillaBlocks::OAK_LOG()->asItem()));
            $items[0]->setCustomName("changed");
            $restored = $serializer::deserializeContents($slots);
            self::assertSame([2], array_keys($restored));
            self::assertTrue($restored[2]->equalsExact($sword));
        }
    }

    public function testCacheEvictsLeastRecentlyUsed() : void{
        $cache = new \ReflectionProperty(SnbtItemSerializer::class, 'itemCache');
        SnbtItemSerializer::enableCache(2);
        $a = SnbtItemSerializer::serialize(VanillaItems::APPLE());
        $b = SnbtItemSerializer::serialize(VanillaItems::STICK());
        $c = SnbtItemSerializer::serialize(VanillaItems::PAPER());
        SnbtItemSerializer::deserialize($a);
        SnbtItemSerializer::deserialize($b);
        SnbtItemSerializer::deserialize($a); // a becomes most recently used
        SnbtItemSerializer::deserialize($c); // evicts b
        $names = array_map(fn($i) => $i->getVanillaName(), array_values($cache->getValue()));
        self::assertSame(["Apple", "Paper"], $names);

        SnbtItemSerializer::enableCache(1); // shrinking trims
        self::assertCount(1, $cache->getValue());
        SnbtItemSerializer::clearCache();
        self::assertCount(0, $cache->getValue());
        SnbtItemSerializer::deserialize($a);
        SnbtItemSerializer::disableCache();
        self::assertCount(0, $cache->getValue());
        SnbtItemSerializer::deserialize($a);
        self::assertCount(0, $cache->getValue(), "disabled cache must not store");
    }

    public function testCacheIsPerFormat() : void{
        SnbtItemSerializer::enableCache();
        BinaryItemSerializer::deserialize(BinaryItemSerializer::serialize(VanillaItems::APPLE()));
        self::assertCount(0, (new \ReflectionProperty(BinaryItemSerializer::class, 'itemCache'))->getValue());
    }

    public function testInvalidInputIsNotCached() : void{
        SnbtItemSerializer::enableCache();
        foreach([1, 2] as $_){
            try{
                SnbtItemSerializer::deserialize('{Count:1b,Name:"minecraft:not_an_item",Damage:0s}');
                self::fail("expected exception");
            }catch(SavedDataLoadingException){
            }
        }
        self::assertCount(0, (new \ReflectionProperty(SnbtItemSerializer::class, 'itemCache'))->getValue());
    }

    public function testInvalidCapacityRejected() : void{
        $this->expectException(\InvalidArgumentException::class);
        SnbtItemSerializer::enableCache(0);
    }
}

<?php

/**
 * Test-only, minimal pure-PHP stand-in for the pmmp/ext-encoding extension.
 *
 * Only fixed-width pack/unpack helpers are provided, which is all that item (de)serialization touches
 * when no world or network code is involved. It is NEVER loaded when the real extension is available.
 */

declare(strict_types=1);

namespace pmmp\encoding;

final class DataDecodeException extends \RuntimeException{
}

/** @internal */
abstract class EncodingPolyfill{
    /** @var array<string, array{string, int, bool}> type => [pack format, size, signed] */
    private const TYPES = [
        'SignedByte' => ['c', 1, true], 'UnsignedByte' => ['C', 1, false],
        'SignedShort' => ['s', 2, true], 'UnsignedShort' => ['v', 2, false],
        'SignedInt' => ['l', 4, true], 'UnsignedInt' => ['V', 4, false],
        'SignedLong' => ['q', 8, true], 'UnsignedLong' => ['P', 8, false],
        'Float' => ['g', 4, true], 'Double' => ['e', 8, true],
    ];

    abstract protected static function bigEndian() : bool;

    /** @param mixed[] $args */
    public static function __callStatic(string $name, array $args) : mixed{
        if(preg_match('/^(pack|unpack)(.+)$/', $name, $m) !== 1 || !isset(self::TYPES[$m[2]])){
            throw new \BadMethodCallException("pmmp\\encoding polyfill does not implement " . static::class . "::$name()");
        }
        [, $size] = self::TYPES[$m[2]];
        $isFloat = $m[2] === 'Float' || $m[2] === 'Double';
        if($m[1] === 'pack'){
            $bytes = $isFloat ? pack(self::TYPES[$m[2]][0], $args[0]) : substr(pack('P', $args[0]), 0, $size);
            return static::bigEndian() ? strrev($bytes) : $bytes;
        }
        $bytes = (string) $args[0];
        if(strlen($bytes) < $size){
            throw new DataDecodeException("Not enough bytes");
        }
        $bytes = substr($bytes, 0, $size);
        if(static::bigEndian()){
            $bytes = strrev($bytes);
        }
        return unpack(self::TYPES[$m[2]][0], $bytes)[1];
    }
}

final class LE extends EncodingPolyfill{
    protected static function bigEndian() : bool{ return false; }
}

final class BE extends EncodingPolyfill{
    protected static function bigEndian() : bool{ return true; }
}

final class ByteBufferReader{
    public function __construct(private string $data, private int $offset = 0){}

    public function getData() : string{ return $this->data; }

    public function getOffset() : int{ return $this->offset; }

    public function setOffset(int $offset) : void{ $this->offset = $offset; }

    public function getUnreadLength() : int{ return strlen($this->data) - $this->offset; }

    public function readByteArray(int $length) : string{
        if($this->getUnreadLength() < $length){
            throw new DataDecodeException("Not enough bytes");
        }
        $result = substr($this->data, $this->offset, $length);
        $this->offset += $length;
        return $result;
    }
}

final class VarInt{
    public static function readUnsignedInt(ByteBufferReader $reader) : int{
        $value = 0;
        for($shift = 0; $shift < 35; $shift += 7){
            $byte = ord($reader->readByteArray(1));
            $value |= ($byte & 0x7f) << $shift;
            if(($byte & 0x80) === 0){
                return $value;
            }
        }
        throw new DataDecodeException("VarInt did not terminate after 5 bytes");
    }

    public static function readSignedInt(ByteBufferReader $reader) : int{
        $raw = self::readUnsignedInt($reader);
        return ($raw >> 1) ^ -($raw & 1);
    }
}

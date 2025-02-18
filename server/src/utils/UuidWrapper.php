<?php
namespace Jsonpost\utils;



use Ramsey\Uuid\{Uuid,UuidInterface};
use \Exception as Exception;


class UuidWrapper {
    private UuidInterface $uuid;
    public static function bin2text(string $bin): string {
        return UUID::fromBytes($bin)->toString();
    }
    public static function text2bin(string $bin): string {
        return UUID::fromString($bin)->getBytes();
    }        // UUIDを生成する静的メソッド
    public static function create7(): UuidWrapper {
        // Uuid::uuid7() は、Ramsey UUID パッケージに基づいてUUIDを生成します
        return new UuidWrapper(Uuid::uuid7());
    }

    // バイト列からUUIDをロードする静的メソッド
    public static function loadFromBytes(string $bytes): UuidWrapper {
        // バイト列からUUIDを再構築
        $uuid = UUID::fromBytes($bytes);
        return new UuidWrapper($uuid);
    }

    // コンストラクタ：UUIDオブジェクトを受け取る
    private function __construct(UuidInterface $uuid) {
        $this->uuid = $uuid;
    }

    // UUIDをバイト列として取得
    public function asBytes(): string {
        return $this->uuid->getBytes();
    }

    // UUIDを16進数文字列として取得
    public function asHex(): string {
        return $this->uuid->getHex();
    }

    // UUIDをテキスト形式として取得（標準的なUUIDの表現）
    public function asText(): string {
        return $this->uuid->toString();
    }

    // UUIDを表示するデバッグ用メソッド
    public function __toString(): string {
        return $this->uuid->toString();
    }
}

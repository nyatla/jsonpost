import ecdsa.util
import os
import hashlib
from typing import Optional,Tuple,List
from dataclasses import dataclass, field,InitVar
import ecdsa

class EcdsaSignner:
    """ ECDSAの署名、検証、復元機能を提供するラッパーです。
    """
    _private_key:ecdsa.SigningKey
    @classmethod
    def generateKey(cls)->bytes:
        """ Privateキーを生成する。
        """
        return ecdsa.SigningKey.generate(curve=ecdsa.SECP256k1).to_string()    
    def __init__(self,src:bytes):
        self._private_key=ecdsa.SigningKey.from_string(src, curve=ecdsa.SECP256k1)
    @property
    def private_key(self)->bytes:
        return self._private_key.to_string()
    @property
    def public_key(self)->bytes:
        sk = self._private_key.get_verifying_key()
        return sk.to_string()
    def sign(self, data_to_sign: bytes) -> bytes:
        """署名を生成して返す。署名はdata_to_signのSHA256ハッシュに対して行う。
        """
        sk = self._private_key
        # print("DTS",data_to_sign.hex(),len(data_to_sign))
        digest = hashlib.sha256(data_to_sign).digest()
        # print("sha256",digest.hex())
        # print("IN-DIGEST",digest.hex())
        # raw形式（固定長）の署名を生成（sigencode_string: 64バイト）
        # Kを固定すればrecoverの署名の出現順位を制御できるみたいだけど理屈がわからない。
        signature = sk.sign_digest(digest, sigencode=ecdsa.util.sigencode_string)
        return signature
    def detectRecoverId(self,signature:bytes,message:bytes)->int:
        """ リカバリIDを得る
        """
        r=self.recover(signature,message)
        # print([i.hex() for i in r])        
        return r.index(self.public_key)
    
    @classmethod
    def recover(cls, signature: bytes, message: bytes) -> Tuple[bytes]:
        """ 署名から公開鍵の候補を全て返す
        """
        digest = hashlib.sha256(message).digest()
        # print("dgs",digest.hex())
        # 復元候補のリストを取得（デフォルトでは sigdecode_string を利用）
        verifying_keys = ecdsa.VerifyingKey.from_public_key_recovery_with_digest(
            signature, digest, curve=ecdsa.SECP256k1, sigdecode=ecdsa.util.sigdecode_string
        )
        if not verifying_keys:
            raise ValueError("recover failed")
        return [i.to_string() for i in verifying_keys]
    @classmethod
    def verify(cls, signature: bytes, message: bytes,pubkey:bytes) -> bool:
        # 公開鍵をVerifyingKeyオブジェクトに変換
        verifying_key = ecdsa.VerifyingKey.from_string(pubkey, curve=ecdsa.SECP256k1)
        # メッセージを SHA256 でハッシュ化
        hashed_msg = hashlib.sha256(message).digest()
        # print(">>",hashed_msg.hex())
        # print(">>",signature.hex())
        # 署名を検証
        return verifying_key.verify_digest(signature, hashed_msg)
    @staticmethod
    def compressPubKey(pubkey:bytes)->bytes:
        """
        非圧縮公開鍵(64バイト)を圧縮公開鍵(32バイト)に変換する。
        
        :param uncompressed_pubkey: 非圧縮公開鍵(65バイト, 0x04 + x(32) + y(32))
        :return: 圧縮公開鍵(33バイト, 0x02/0x03 + x(32))
        """
        if len(pubkey) != 64:
            raise ValueError("非圧縮公開鍵のフォーマットが正しくありません。")

        x = pubkey[0:32]  # x座標（32バイト）
        y = pubkey[32:64]  # y座標（32バイト）

        # y座標の最下位ビット（偶数なら 0x02、奇数なら 0x03）
        prefix = b'\x02' if y[-1] % 2 == 0 else b'\x03'        
        return prefix + x
    @staticmethod
    def decompress_pubkey(compressed_pubkey: bytes) -> bytes:
        """
        圧縮公開鍵(33バイト)を非圧縮公開鍵(65バイト)に展開する。
        
        :param compressed_pubkey: 圧縮公開鍵(33バイト, 0x02/0x03 + x(32))
        :return: 非圧縮公開鍵(65バイト, 0x04 + x(32) + y(32))
        """
        P = 0xFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFEFFFFFC2F
        B = 7
        if len(compressed_pubkey) != 33 or compressed_pubkey[0] not in (0x02, 0x03):
            raise ValueError("圧縮公開鍵のフォーマットが正しくありません。")

        prefix, x = compressed_pubkey[0], int.from_bytes(compressed_pubkey[1:], 'big')

        # y^2 = x^3 + 7 mod p
        y2 = (x**3 + B) % P
        y = pow(y2, (P + 1) // 4, P)  # Tonelli-Shanks で平方根を求める

        # パリティ（偶数/奇数）をチェックして正しい y を選択
        if (y % 2 == 0) != (prefix == 0x02):
            y = P - y
        return b'\x04' + x.to_bytes(32, 'big') + y.to_bytes(32, 'big')



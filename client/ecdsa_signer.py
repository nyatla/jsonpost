import os
import ecdsa.util
import os
import json
import hashlib
from typing import Optional,Tuple,List
from dataclasses import dataclass, field,InitVar
import ecdsa

@dataclass(frozen=True)
class EcdsaSignner:
    _private_key:ecdsa.SigningKey=field(init=False)
    _recover_id:Optional[int]=field(init=False)
    src: InitVar[bytes|None] = None
    def __post_init__(self,src:bytes):
        object.__setattr__(self,"_private_key",ecdsa.SigningKey.from_string(src, curve=ecdsa.SECP256k1))
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
        print("DTS",data_to_sign,len(data_to_sign))
        digest = hashlib.sha256(data_to_sign).digest()
        print("IN-DIGEST",digest.hex())
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
        print("dgs",digest.hex())
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
        print(">>",hashed_msg.hex())
        print(">>",signature.hex())
        # 署名を検証
        return verifying_key.verify_digest(signature, hashed_msg)



class EasyEcdsaStreamBuilder:
    """ パッケージ化したEcdsa署名文字列生成クラスです。
        署名、圧縮公開鍵、メッセージリカバリIDを一体化したバイトストリームを生成します。
    """
    def __init__(self,pk:bytes):
        self._ecs=EcdsaSignner(pk)
    @classmethod
    def generateKey(cls)->bytes:
        """ Privateキーを生成する。
        """
        return ecdsa.SigningKey.generate(curve=ecdsa.SECP256k1).to_string()
    @staticmethod
    def compressPubKey(pubkey:bytes)->bytes:
        """
        非圧縮公開鍵（64バイト）を圧縮公開鍵（32バイト）に変換する。
        
        :param uncompressed_pubkey: 非圧縮公開鍵（65バイト, 0x04 + x(32) + y(32)）
        :return: 圧縮公開鍵（33バイト, 0x02/0x03 + x(32)）
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
        圧縮公開鍵（33バイト）を非圧縮公開鍵（65バイト）に展開する。
        
        :param compressed_pubkey: 圧縮公開鍵（33バイト, 0x02/0x03 + x(32)）
        :return: 非圧縮公開鍵（65バイト, 0x04 + x(32) + y(32)）
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
    def encode(self, data: bytes=None) -> bytes:
        """ signature,pubkey,dataの順で格納します。pubkeyは圧縮キーです。
        """
        ecs=self._ecs
        signature = ecs.sign(data)
        self.compressPubKey(ecs.public_key)
        return signature+self.compressPubKey(ecs.public_key)+data
    @classmethod
    def decode(cls, encoded_data: bytes=None) -> Tuple[bytes,bytes]:
        """エンコードしたデータから、pubkey,dataを復帰します。
        """
        assert(len(encoded_data)>64+33)
        assert(encoded_data[64] in [0x02,0x03,0x04])
        sign=encoded_data[:64]
        pubkey=cls.decompress_pubkey(encoded_data[64:64+33])[1:] #先頭の04はいらない
        data=encoded_data[64+33:]
        # pubkey=bytes.fromhex('f03ce7b379a0472534fb2a7c5c9b69008d0b02a77cf922a9aa59a98c9381eeed7315b166418e0574764d102d2234498629bb20257ef6d95060e951928da69d79')//違うKeyでエラーOK
        # data=encoded_data[64+34:]#違うデータでエラーOK
        # sign=encoded_data[:63]+b'0'#壊れたsignでエラーOK
        
        if not EcdsaSignner.verify(sign,data,pubkey):
            return None
        else:
            return pubkey,data



# import unittest

# class TestPubKeyCompression(unittest.TestCase):

#     def test_compress_decompress(self):
#         """ 圧縮・展開して元に戻ることを確認 """
#         # 65バイトの非圧縮公開鍵（04 + x(32) + y(32)）
#         uncompressed_pubkey = bytes.fromhex(
#             "04f03ce7b379a0472534fb2a7c5c9b69008d0b02a77cf922a9aa59a98c9381eeed7315b166418e0574764d102d2234498629bb20257ef6d95060e951928da69d79"
#         )
#         # 先頭の 0x04 を除いた 64バイトの鍵
#         pubkey_64 = uncompressed_pubkey[1:]

#         # 圧縮
#         compressed = EasyEcdsaStreamBuilder.compressPubKey(pubkey_64)
#         self.assertEqual(len(compressed), 33)
#         self.assertIn(compressed[0], (0x02, 0x03))  # 先頭は 0x02 or 0x03

#         # 展開
#         decompressed = EasyEcdsaStreamBuilder.decompress_pubkey(compressed)
#         self.assertEqual(decompressed, uncompressed_pubkey)  # 元の値と一致

#     def test_invalid_compress(self):
#         """ 無効な入力を圧縮しようとするとエラー """
#         with self.assertRaises(ValueError):
#             EasyEcdsaStreamBuilder.compressPubKey(b'\x00' * 63)  # 64バイト未満

#     def test_invalid_decompress(self):
#         """ 無効な入力を展開しようとするとエラー """
#         with self.assertRaises(ValueError):
#             EasyEcdsaStreamBuilder.decompress_pubkey(b'\x04' + b'\x00' * 32)  # 先頭が 0x02/0x03 でない
#         with self.assertRaises(ValueError):
#             EasyEcdsaStreamBuilder.decompress_pubkey(b'\x02' + b'\x00' * 31)  # 33バイト未満

# if __name__ == '__main__':
#     unittest.main()




# # for i in range(100):    
# pk=bytes.fromhex("59a94c947057c92a4ebd2b6f85d90ff3a862e2bbc8df1a1eb0f68dee69634af9")
# # pk=os.urandom(32)
# eeesb=EasyEcdsaStreamBuilder(pk)
# S=os.urandom(4)
# print("sdata    ",S.hex())
# ss=eeesb.encode(S)
# print("encoded  ",ss.hex())

a,d=EasyEcdsaStreamBuilder.decode(bytes.fromhex('5fde0a19900e5e3e943f501fdaa36e49ef53a3e2d31b0c58db6c5b636a1f0269d2c0b9341eddac35afe88e249b211ae8e50cb35cfa52be56d2b0472ba3e050a803f03ce7b379a0472534fb2a7c5c9b69008d0b02a77cf922a9aa59a98c9381eeed2f43a3f2'))
# print("pubkey(O)",eeesb._ecs.public_key.hex())
print("pubkey(R)",a.hex())
print("pubkey(S)",EasyEcdsaStreamBuilder.compressPubKey(a).hex())
print("ddata    ",d.hex())

# assert(a.hex()==eeesb._ecs.public_key.hex())




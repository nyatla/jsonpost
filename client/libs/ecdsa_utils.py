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
        # print("DTS",data_to_sign,len(data_to_sign))
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


@dataclass(frozen=True)
class EasyEcdsaSignature:    
    ecdsasignature:bytes
    pubkey:bytes
    data:bytes
    @property    
    def rawkey(self)->bytes:
        return EcdsaSignner.decompress_pubkey(self.pubkey)[1:]
    @property    
    def signature(self)->bytes:
        return self.ecdsasignature+self.pubkey+self.data
    @classmethod
    def fromBytes(cls,d:bytes)->"EasyEcdsaSignature":
        assert(len(d)>64+33)
        assert(d[64] in [0x02,0x03,0x04])        
        return EasyEcdsaSignature(
            d[:64],            
            d[64:64+33],
            d[64+33:])  



class EasyEcdsaSignatureBuilder:
    """ パッケージ化したEcdsa署名文字列生成クラスです。
        署名、圧縮公開鍵、データを一体化したバイトストリームのエンコーダ/デコーダです。
    """
    def __init__(self,pk:bytes):
        self._ecs=EcdsaSignner(pk)
    @classmethod
    def generateKey(cls)->bytes:
        """ Privateキーを生成する。
        """
        return ecdsa.SigningKey.generate(curve=ecdsa.SECP256k1).to_string()

    def encode(self, data: bytes=None) -> EasyEcdsaSignature:
        """ signature,pubkey,dataの順で格納します。pubkeyは圧縮キーです。
        """
        ecs=self._ecs
        return EasyEcdsaSignature(
            ecs.sign(data),
            EcdsaSignner.compressPubKey(ecs.public_key),
            data)
    @classmethod
    def decode(cls, encoded_data: bytes=None) -> EasyEcdsaSignature:
        """エンコードしたデータから、pubkey,dataを復帰します。
        """
        es=EasyEcdsaSignature.fromBytes(encoded_data)
        # pubkey=bytes.fromhex('f03ce7b379a0472534fb2a7c5c9b69008d0b02a77cf922a9aa59a98c9381eeed7315b166418e0574764d102d2234498629bb20257ef6d95060e951928da69d79')//違うKeyでエラーOK
        # data=encoded_data[64+34:]#違うデータでエラーOK
        # sign=encoded_data[:63]+b'0'#壊れたsignでエラーOK
        
        if not EcdsaSignner.verify(es.ecdsasignature,es.data,EcdsaSignner.decompress_pubkey(es.pubkey)[1:]):
            return None
        else:
            return es


@dataclass(frozen=True)
class PowEcdsaSignature:
    ees:EasyEcdsaSignature
    pownonce:int
    @property
    def signature(self):
        return self.ees.signature+int.to_bytes(self.pownonce,4,'big')
    @property
    def powbits(self)->int:
        return self.countPowbits(hashlib.sha256(hashlib.sha256(self.signature).digest()).digest())
    @property
    def sha256d(self)->bytes:
        return hashlib.sha256(hashlib.sha256(self.signature).digest()).digest()
    @classmethod
    def countPowbits(cls, data: bytes) -> int:
        bit_count = 0
        for b in data:
            if b == 0:
                bit_count += 8
            else:
                bit_count += (8 - b.bit_length())
                break
        return bit_count
    @classmethod
    def fromBytes(cls,d:bytes)->"PowEcdsaSignature":
        return PowEcdsaSignature(EasyEcdsaSignature.fromBytes(d[:-4]),int.from_bytes(d[-4:],'big'))
        

class PowEcdsaSignatureBuilder:
    """Powフィールドを追加した署名です。
    """
    def __init__(self,pk:bytes):
        self._ecs=EasyEcdsaSignatureBuilder(pk)

    def encode(self,data:bytes,zerobits:int)->PowEcdsaSignature:
        """ sha256d(sign+pownonce)の下位zerobits以上が0になる32bitのpownonceをハッシングして、
            signed+pownonceを連結したbytesを返します。
        """
        signed=self._ecs.encode(data)
        #ハッシング
        for i in range(0xffffffff):
            pes=PowEcdsaSignature(signed,i)
            if pes.powbits>=zerobits:
                return pes
        return None
    @classmethod
    def decode(cls, encoded_data: bytes=None) -> PowEcdsaSignature:
        return PowEcdsaSignature.fromBytes(encoded_data)

# pesb=PowEcdsaSignatureBuilder(os.urandom(32))
# r=pesb.encode(b"hell",16)
# print(r.pownonce)
# print(r.sha256d.hex())
# r2=PowEcdsaSignature.fromBytes(r.signature)
# print(len(r.signature))
# print(r.signature.hex())
# print(r2.pownonce)
# print(r2.sha256d.hex())
# print(r2.ees.data)
# assert(r==r2)

#ERR
# r2=PowEcdsaSignature.fromBytes(bytes.fromhex('03301c08510469b0f4361ba65c0184a280df54246062ab249ffa637cd9435e3f53ba2d00701e8579cf45341261adca6b2fd8a79b73e5cb16e1d49ab499597df303c2436c9613ac23192a26f32e874283b0271a39138747b2fdcf8fe1377bc75c622f479c19'))
# print(r2.pownonce)
# print(r2.sha256d.hex())


# es=EcdsaSignner(bytes.fromhex('b79678e0d98bb60d0727709a54359a7d7cbb17a7d618f5c19d851245ca5adc5c'))
# encd=es.sign(b"123")
# print(es.public_key.hex())
# print(encd.hex())
# # decd=EasyEcdsaSignatureBuilder.decode(encd.signature)
# # print("pubkey(R)",es._ecs.public_key.hex())
# # print("pubkey(S)",decd.rawkey.hex())
# # print("ddata    ",decd.data.hex())

# print(es.verify(
#     bytes.fromhex('40d7d238bcec0c83dc64d6580faf30736c8d1969b21de6a8d26d9cbeb428f47a6c787453a3b290f74ef69c8a71f7872e72c15b77d861d3741f3a1af80649d821'),
#     b"123",
#     es.public_key
# ))





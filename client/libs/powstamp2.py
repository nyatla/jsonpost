
# import os,sys
# sys.path.append(os.path.join(os.path.dirname(__file__), '.'))

import hashlib
from dataclasses import dataclass, field
from typing import ClassVar,Optional,List,Callable,Generator
import struct
import time
from .ecdsa_utils import EcdsaSignner

@dataclass(frozen=True)
class PowStamp2Message:
    """
    EcdsaPublicKey	33	プレフィクス付キー
    Nonce	6	メッセージNonce
    ServerDomainHash(sha256)	32	
    PayloadHash(sha256)	32	
    total	103
    """
    message:bytes
    @property
    def ecdsaPubkey(self)->bytes:
        return self.message[0:33]
    @property
    def nonce(self)->bytes:
        return self.message[33:33+6]
    
    @property
    def chainHash(self)->bytes:
        return self.message[33+6:33+6+32]
    @property
    def payloadHash(self)->bytes:
        return self.message[33+6+32:33+6+32+32]
    @classmethod
    def create(cls,pubkey:bytes,nonce:bytes,chain_hash:bytes,payload_hash:Optional[bytes]=None):
        assert(len(pubkey)==33)
        assert(len(nonce)==6)
        b=pubkey+nonce
        # print("s",server_domain_hash)
        # print("p",payload_hash.hex())
        b+=chain_hash
        if payload_hash is not None:
            b+=payload_hash
        else:
            b+= b'\x00' * 32
        return PowStamp2Message(b)

    @property
    def hash(self)->bytes:
        return hashlib.sha256(hashlib.sha256(self.message).digest()).digest()

    @property
    def powScoreU48(self) -> int:
        return int.from_bytes(self.hash[:6], byteorder='big')



@dataclass(frozen=True)
class PowStamp2:
    """
    フィールド名	サイズ(byte)	
    PowStampSignature	64	SHA256
    EcdsaPublicKey	33	プレフィクス付キー
    Nonce	6	メッセージ/ハッシングNonce
    total	103	
    """
    stamp:bytes
    @property
    def nonce(self)->bytes:
        return self.stamp[64+33:64+33+6]
    @property
    def nonceAsU48(self)->int:
        return int.from_bytes(self.nonce,'big')
    @property
    def powStampSignature(self)->bytes:
        return self.stamp[0:64]
    @property
    def ecdsaPubkey(self)->bytes:
        return self.stamp[64:64+33]

    def recoverMessage(self,chain_hash:bytes,payload:Optional[bytes]=None):
        """ server_domain,payloadを加えてMessageを復帰する。
        """
        return PowStamp2Message.create(
            self.ecdsaPubkey,
            self.nonce,
            chain_hash,
            None if payload is None else hashlib.sha256(payload).digest()            
        )    


    @classmethod
    def verify(cls,stamp:"PowStamp2",chain_hash:bytes,payload:Optional[bytes]=None)->bool:
        psm=stamp.recoverMessage(chain_hash,payload)
        return EcdsaSignner.verify(
            stamp.powStampSignature,
            psm.message,
            stamp.ecdsaPubkey)


class PowStamp2Builder:
    MAX_NONCE=0x0000ffffffffffff #48bit

    es:EcdsaSignner
    def __init__(self,pk:bytes):
        self.es=EcdsaSignner(pk)

    def createStampMessageGenerator(self, nonce_start: int, chain_hash: bytes, payload: Optional[bytes] = None) -> Generator[PowStamp2Message, None, None]:
        """ハッシング結果を返すgeneratorです
        """
        assert(len(chain_hash)==32)
        es=self.es
        pubkey=EcdsaSignner.compressPubKey(es.public_key)
        for i in range(nonce_start,self.MAX_NONCE):
            yield PowStamp2Message.create(
                pubkey,
                struct.pack('>Q', i)[2:8],#48bit
                chain_hash,
                None if payload is None else hashlib.sha256(payload).digest()
            )
        raise Exception("Nonce overflow.")
    
    def createStamp(self,nonce_start:int,chain_hash:bytes,payload:Optional[bytes]=None,target_score:Optional[int]=None) -> PowStamp2:
        """ target_scoreを満たすPowをハッシングして返す。
        """
        es=self.es
        spubkey=EcdsaSignner.compressPubKey(es.public_key)
        for message in self.createStampMessageGenerator(nonce_start,chain_hash,payload):
            if target_score is None or message.powScoreU48<=target_score:
                return PowStamp2(es.sign(message.message)+spubkey+message.nonce)
        raise ValueError(f"Failed to find a valid PoW nonce for target_score={target_score}")
    
    def createStampFromMessage(self,message:PowStamp2Message) -> PowStamp2:
        """ target_scoreを満たすPowをハッシングして返す。
        """
        es=self.es
        return PowStamp2(es.sign(message.message)+message.ecdsaPubkey+message.nonce)







# #ドメインとペイロード無し
# pk=bytes.fromhex('0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef')
# psb=PowStampBuilder(pk)
# ps=psb.encode(0)
# assert(ps.nonceAsInt==0)
# assert(ps.ecdsaPubkey==EcdsaSignner.compressPubKey(psb.es.public_key))
# assert(PowStamp.verify(ps))

# #ドメインとペイロード
# ps=psb.encode(2,server_domain="localhost",payload=b"testtttt",target_score=10)
# print(ps.score)
# assert(ps.nonceAsInt==2)
# assert(ps.ecdsaPubkey==EcdsaSignner.compressPubKey(psb.es.public_key))
# assert(PowStamp.verify(ps,server_domain="localhost",payload=b"testtttt"))


# #nonce書換→エラー
# ps=psb.encode(2,server_domain="localhost",payload=b"testtttt",target_score=10)
# ps=PowStamp(ps.stamp[0:64+33]+b'\0'*4+ps.stamp[64+33+4:])
# print(ps.score,ps.nonceAsInt)
# assert(ps.nonceAsInt==0)
# assert(ps.ecdsaPubkey==EcdsaSignner.compressPubKey(psb.es.public_key))
# assert(PowStamp.verify(ps,server_domain="localhost",payload=b"testtttt"))

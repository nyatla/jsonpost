
# import os,sys
# sys.path.append(os.path.join(os.path.dirname(__file__), '.'))

import hashlib
from dataclasses import dataclass, field
from typing import ClassVar,Optional,List
import struct

from .ecdsa_utils import EcdsaSignner

@dataclass(frozen=True)
class PowStampMessage:
    """
    EcdsaPublicKey	33	プレフィクス付キー
    Nonce	4	メッセージNonce
    ServerDomainHash(sha256)	32	
    PayloadHash(sha256)	32	
    total	101
    """
    message:bytes
    @property
    def ecdsaPubkey(self)->bytes:
        return self.message[0:33]
    @property
    def nonce(self)->bytes:
        return self.message[33:33+4]
    
    @property
    def serverDomainHash(self)->bytes:
        return self.message[33+4:33+4+32]
    @property
    def payloadHash(self)->bytes:
        return self.message[33+4+32:33+4+32+32]
    @classmethod
    def create(cls,pubkey:bytes,nonce:bytes,server_domain_hash:Optional[bytes]=None,payload_hash:Optional[bytes]=None):
        assert(len(pubkey)==33)
        b=pubkey+nonce
        # print("s",server_domain_hash)
        # print("p",payload_hash.hex())
        if server_domain_hash is not None:
            b+=server_domain_hash
        else:
            b+= b'\x00' * 32
        if payload_hash is not None:
            b+=payload_hash
        else:
            b+= b'\x00' * 32
        return PowStampMessage(b)



@dataclass(frozen=True)
class PowStamp:
    """
    フィールド名	サイズ(byte)	
    PowStampSignature	64	SHA256
    EcdsaPublicKey	33	プレフィクス付キー
    Nonce	4	メッセージNonce
    PowNonce	4	ハッシングNonce
    total	105	
    """
    stamp:bytes
    @property
    def powNonce(self)->bytes:
        return self.stamp[64+33+4:64+33+4+4]
    @property
    def powNonceAsInt(self)->int:
        return int.from_bytes(self.powNonce,'big')
    @property
    def nonce(self)->bytes:
        return self.stamp[64+33:64+33+4]
    @property
    def nonceAsInt(self)->int:
        return int.from_bytes(self.nonce,'big')
    @property
    def powStampSignature(self)->bytes:
        return self.stamp[0:64]
    @property
    def ecdsaPubkey(self)->bytes:
        return self.stamp[64:64+33]


    @property
    def hash(self)->bytes:
        return hashlib.sha256(hashlib.sha256(self.stamp).digest()).digest()


    @property
    def score(self):
        """ stampのbytesの先頭からの0ビットの数を数える
        """
        h=self.hash
        bit_count = 0
        for b in h:
            if b == 0:
                bit_count += 8
            else:
                bit_count += (8 - b.bit_length())
                break
        return bit_count


    @classmethod
    def verify(cls,stamp:"PowStamp",server_domain:Optional[str]=None,payload:Optional[bytes]=None)->bool:
        psm=PowStampMessage.create(
            stamp.ecdsaPubkey,
            stamp.nonce,
            None if server_domain is None else hashlib.sha256(server_domain.encode()).digest(),
            None if payload is None else hashlib.sha256(payload).digest()            
            )
        return EcdsaSignner.verify(
            stamp.powStampSignature,
            psm.message,
            stamp.ecdsaPubkey)
    

class PowStampBuilder:
    es:EcdsaSignner
    def __init__(self,pk:bytes):
        self.es=EcdsaSignner(pk)
    def encode(self,nonce:int,server_domain:Optional[str]=None,payload:Optional[bytes]=None,target_score:int=0) -> PowStamp:
        """ signature,pubkey,dataの順で格納します。pubkeyは圧縮キーです。
        """
        es=self.es
        spubkey=EcdsaSignner.compressPubKey(es.public_key)
        sm=PowStampMessage.create(
            spubkey,
            struct.pack('>I', nonce),
            None if server_domain is None else hashlib.sha256(server_domain.encode()).digest(),
            None if payload is None else hashlib.sha256(payload).digest()
        )
        hash_base=es.sign(sm.message)+spubkey+struct.pack('>I',nonce)
        for i in range(0xffffffff):
            pw=PowStamp(hash_base+struct.pack('>I', i))
            if pw.score>target_score:
                return pw
        raise ValueError(f"Failed to find a valid PoW nonce for target_score={target_score}")



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

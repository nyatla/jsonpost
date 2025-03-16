import os,sys
sys.path.append(os.path.join(os.path.dirname(__file__), '..'))
from libs.powstamp2 import PowStamp2,PowStamp2Builder,PowStamp2Message
from libs.ecdsa_utils import EcdsaSignner


#ドメインとペイロード無し
pk=bytes.fromhex('0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef')
psb=PowStamp2Builder(pk)
ps=psb.createStamp(0,target_score=0xffffffffffffffff)
assert(ps.nonceAsU48==0)
assert(ps.ecdsaPubkey==EcdsaSignner.compressPubKey(psb.es.public_key))
assert(PowStamp2.verify(ps))

#ドメインとペイロード
ps=psb.createStamp(2,server_domain="localhost",payload=b"testtttt",target_score=0x000000ffffffffff)
print(ps.nonceAsU48)
assert(ps.ecdsaPubkey==EcdsaSignner.compressPubKey(psb.es.public_key))
assert(PowStamp2.verify(ps,chain_hash="localhost",payload=b"testtttt"))
rm=ps.recoverMessage(server_domain="localhost",payload=b"testtttt")
print(rm.payloadHash.hex(),rm.powScoreU48)

# #nonce書換→エラー
ps=psb.createStamp(2,server_domain="localhost",payload=b"testtttt",target_score=0x00ffffffffffffff)
print(len(ps.stamp))
# self.createStampMessage2Generator(nonce,server_domain,payload)
ps=PowStamp2(ps.stamp[0:64+33]+b'\0'*8+ps.stamp[64+33+8:])
print(ps.nonceAsU48)
# assert(ps.nonceAsInt==0)
assert(ps.ecdsaPubkey==EcdsaSignner.compressPubKey(psb.es.public_key))
assert(PowStamp2.verify(ps,chain_hash="localhost",payload=b"testtttt"))#NGOK

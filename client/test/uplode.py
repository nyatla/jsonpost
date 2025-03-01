import os,sys
sys.path.append(os.path.join(os.path.dirname(__file__), '..'))
from jsonpostcl import JsonpostCl

for i in range(1):
    JsonpostCl.main(f"upload http://127.0.0.1:8000/api {{\"key2\":\"aaa{i}\"}} --config ./jsonpost.cfg.json --powbits 1 --nonce 10 --verbose".split(" "))
    import time;        
    time.sleep(1)

# import hashlib

# s=bytes.fromhex("df04b516c6fa4aa4f4beb2173cbfffb60d8747ffd2cbf9725517b4c2fe8e16b173170c692f19a59472b2b5f5eeaa5e1a5ab6c48b79b19b4e715226be1007609703af5bf52aa42e6f23b4b30c1ee4e08dff72ea05e59c8d873014d8ad1f45f949d22f47c99800000003")
# print(hashlib.sha256(hashlib.sha256(s).digest()).digest().hex())


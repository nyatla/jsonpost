import os,sys
sys.path.append(os.path.join(os.path.dirname(__file__), '..'))
from jsonpostcl import JsonpostCl


JsonpostCl.main("konnichiwa http://127.0.0.1:8000/api --welcome false --json-jcs yes --json-schema ./schema.json --pow-algorithm [\"tlsln\",[1,0.01,3.0]]".split(" "))
# JsonpostCl.main("konnichiwa https://nyatla.jp/jsonpost/1/server/public/api --welcome false --json-jcs no --pow-algorithm [\"tlsln\",[1,0.01,3.0]]".split(" "))

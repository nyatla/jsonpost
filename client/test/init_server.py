import os,sys
sys.path.append(os.path.join(os.path.dirname(__file__), '..'))
from jsonpostcl import JsonpostCl


# JsonpostCl.main("konnichiwa http://127.0.0.1:8000/api --welcome false --json-jcs yes --json-schema ./schema.json".split(" "))
JsonpostCl.main("konnichiwa http://127.0.0.1:8000/api --welcome false --json-jcs no --pow-algorithm [\"tlsln\",[1,0.01,3.0]]".split(" "))

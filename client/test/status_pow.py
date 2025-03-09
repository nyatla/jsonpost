import os,sys
sys.path.append(os.path.join(os.path.dirname(__file__), '..'))
from jsonpostcl import JsonpostCl

JsonpostCl.main("status http://127.0.0.1:8000/api -U -A --verbose".split(" "))

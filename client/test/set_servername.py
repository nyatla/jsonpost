import os,sys
sys.path.append(os.path.join(os.path.dirname(__file__), '..'))
from jsonpostcl import JsonpostCl


    # # args = parser.parse_args()
    
    # # args = parser.parse_args("version http://127.0.0.1:8000/api".split(" "))
    # # args = parser.parse_args("init".split(" "))
    # # args = parser.parse_args("upload http://127.0.0.1:8000/api {\"key\":\"valueあああ\"} --config ./jsonpost.cfg.json --verbose".split(" "))
    # # args = parser.parse_args("upload http://127.0.0.1:8000/api -j {\"key\":\"valuew\"} --config ./jsonpost.cfg.json --nonce 12357 --verbose".split(" "))
    # # args = parser.parse_args("upload http://127.0.0.1:8000/api -f ./jsonpost.cfg.json --config ./jsonpost.cfg.json --nonce 12349 --verbose".split(" "))
    # args = parser.parse_args("konnichiwa http://127.0.0.1:8000/api --params_default_diff_bits 4".split(" "))
    # # 実行するコマンドを決定
    # if args.command:
    #     command_class = args.func(args)
    #     command_class.execute()
    # else:
    #     parser.print_help()
    # # for i in range(10):
    # #     args = parser.parse_args(f"upload http://127.0.0.1:8000/api {{\"key\":\"あああaaa{i}\"}} --config ./jsonpost.cfg.json --verbose".split(" "))
    # #     import time;        
    # #     time.sleep(1)

    # #     # 実行するコマンドを決定
    # #     if args.command:
    # #         command_class = args.func(args)
    # #         command_class.execute()
    # #     else:
    # #         parser.print_help()
# JsonpostCl.main("setparams http://127.0.0.1:8000/api --server-name aaa".split(" "))
JsonpostCl.main("setparams http://127.0.0.1:8000/api --pow-algorithm [\"tlsln\",[10,0.1,3.8]]".split(" "))
# JsonpostCl.main("setparams http://127.0.0.1:8000/api --welcome false".split(" "))
# JsonpostCl.main("setparams http://127.0.0.1:8000/api --json-no-schema".split(" "))
# JsonpostCl.main("setparams http://127.0.0.1:8000/api --json-schema ./schema.json".split(" "))
# JsonpostCl.main("setparams http://127.0.0.1:8000/api --json-jcs no".split(" "))
# JsonpostCl.main("setparams http://127.0.0.1:8000/api".split(" "))


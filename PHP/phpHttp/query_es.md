# query_es 的请求参数说明文档
请求数据格式:
```json
{
    "view": {
        "begin": 1000,
        "count": 50,
        "total": 13361319,
        "scroll_id": "DnF1ZXJ5VGhlbkZldGNoBQAAAAAABFuDFnljMEY1YlZPUnlxT1JwYWJmV2lkUFEAAAAAAARbghZ5YzBGNWJWT1J5cU9ScGFiZldpZFBRAAAAAAAEW3QWcm4zOU5HbjhUOFdITXpGUW9YWFhoQQAAAAAABFtzFnJuMzlOR244VDhXSE16RlFvWFhYaEEAAAAAAARbhBZ5YzBGNWJWT1J5cU9ScGFiZldpZFBR"
    },
    "cond": {
        "hrscan_name": "*",
        "msscan": "!"
    },
    "order": [
        {
            "name": "hrscan_name",
            "desc": 0
        }
    ]
}
```
如果没有 scroll_id 则表示是新的请求，如果存在 scroll_id 则表示是分页请求；scroll_id 从返回数据中 view 下的 scroll_id 获取，每次的 scroll_id 都要从返回数据中取，因为每一次的 scroll_id 是不同的。

返回数据格式
```json
{
    "errno": 0,
    "data": {
        "list": [
            {
                "id": 40466287,
                "fdfs_path": "group21/M00/E4/02/wKgBFVl8EZ2Aer6wAAecIKJ-qd02595216",
                "filesize": 498720,
                "md5": "e73a49de3200cb62e98a18fcf9539394",
                "sha1": "68fce88635f11ef02e6e1e870f60bc17b25476e1",
                "sha256": "0389c956ec94a5f978a64a0a3e14009393edfa0dae4a4d1aca099fbec2076528",
                "sha512": "10011e0553a666f76f93fda5655b89566688a9ae3f2cd91bf3f8d9aa9a88d4614e6e92408b02f23265daf16483b14fca38a4a85e271f0340b1e1fcc04f61a394",
                "addtime": "2016-05-16 23:00:12",
                "modtime": "2017-07-29 12:39:57",
                "srcid": 1541,
                "filetype": 1,
                "vid": null,
                "vname": null,
                "hrscan_id": "c4d13e59c677edea",
                "hrscan_name": "Adware/180SA",
                "msscan": null,
                "avpscan": "not-a-virus:AdWare.Win32.180Solutions.as",
                "esetscan": "Win32/Adware.180Solutions application",
                "diescan": null,
                "simhash": "0x0000000b71106b4b",
                "hashsig_hash": "0xa54d5302,0x98ea736a82961930",
                "hashsig_flag": 0,
                "status": 0,
                "analyst": 0,
                "result": 0,
                "rptcnt": 0
            }
        ],
        "view": {
            "total": 13361319,
            "begin": 1000,
            "count": 50,
            "scroll_id": "DnF1ZXJ5VGhlbkZldGNoBQAAAAAABFuDFnljMEY1YlZPUnlxT1JwYWJmV2lkUFEAAAAAAARbghZ5YzBGNWJWT1J5cU9ScGFiZldpZFBRAAAAAAAEW3QWcm4zOU5HbjhUOFdITXpGUW9YWFhoQQAAAAAABFtzFnJuMzlOR244VDhXSE16RlFvWFhYaEEAAAAAAARbhBZ5YzBGNWJWT1J5cU9ScGFiZldpZFBR"
        }
    }
}
```

ElasticSearch 的查询 DSL 基本结构 [] 是数组，可以多个各种条件，比如 term，exists，match，等等。
```php
    // query.bool.[must,must_not,should]
    $query = [
        'must'=>[],
        'must_not'=>[],
        'should'=>[],
    ];
```
logstash 默认使用的时间格式为 ISO8601 其他工具都应该配合 logstash 格式。
PHP 时间转换
```php
    // ISO8601 标准格式，Logstash 使用的格式，为什么使用 ATOM，
    // PHP里因为历史原因，原版的 ISO8601 是不正确的，ATOM 是后来更新的正确的格式。
    $date = date(DateTime::ATOM, strtotime($val));
```
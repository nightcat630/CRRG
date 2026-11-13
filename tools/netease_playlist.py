#!/usr/bin/env python3
"""
网易云歌单导出 v9.0 — weapi 全量拉取
"""

import json, requests, re, csv, sys, time, random, string
from Crypto.Cipher import AES
import base64, codecs

MODULUS = int('00e0b509f6259df8642dbc35662901477df22677ec152b5ff68ace615bb7b725152b3ab17a876aea8a5aa76d2e417629ec4ee341f56135fccf695280104e0312ecbda92557c93870114af6c9d05c4f7f0c3685b7a46bee255932575cce10b424d813cfe4875d3e82047b97ddef52741d546b8e289dc6935b3ece0462db0a22b8e7', 16)
PUBKEY = int('010001', 16)
NONCE = '0CoJUm6Qyw8W8jud'
IV = '0102030405060708'

HEADERS = {
    'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
    'Referer': 'https://music.163.com/',
}

def weapi(data):
    """加密请求参数"""
    text = json.dumps(data)
    # 随机 16 字符 key
    sec_key = ''.join(random.choices(string.ascii_letters + string.digits, k=16))
    
    # AES 加密: 先 nonce 再 sec_key
    cipher1 = AES.new(NONCE.encode(), AES.MODE_CBC, IV.encode())
    enc_text = base64.b64encode(cipher1.encrypt(_pad(text.encode()))).decode()
    
    cipher2 = AES.new(sec_key.encode(), AES.MODE_CBC, IV.encode())
    enc_text = base64.b64encode(cipher2.encrypt(_pad(enc_text.encode()))).decode()
    
    # RSA 加密 sec_key
    rev = sec_key[::-1]
    hex_val = codecs.encode(rev.encode(), 'hex').decode()
    enc_sec_key = format(pow(int(hex_val, 16), PUBKEY, MODULUS), 'x').zfill(256)
    
    return {'params': enc_text, 'encSecKey': enc_sec_key}

def _pad(s, bs=16):
    n = bs - len(s) % bs
    return s + bytes([n] * n)

def main():
    raw = input("粘贴网易云歌单链接: ").strip()
    m = re.search(r'id[=:]\s*(\d+)', raw)
    if not m: print("❌ 没找到 ID"); input(); return
    pid = m.group(1)
    
    sess = requests.Session()
    
    print("正在获取歌单（加密通道）...")
    
    # weapi 获取 playlist/detail
    data = weapi({'id': pid, 'n': 100000, 'csrf_token': ''})
    r = sess.post('https://music.163.com/weapi/v6/playlist/detail',
                  data=data, headers=HEADERS, timeout=20)
    
    try:
        d = r.json()
    except:
        print(f"  响应非 JSON: {r.text[:200]}")
        print("  降级到普通 API...")
        r = sess.get(f'https://music.163.com/api/playlist/detail?id={pid}',
                     headers=HEADERS, timeout=15)
        d = r.json()
    
    pl = d.get('playlist') or d.get('result', {})
    name = pl.get('name', '歌单')
    
    # trackIds
    tid_list = (pl.get('trackIds') or pl.get('topTrackIds') or [])
    if not tid_list:
        # 可能 tracks 里有更多
        tracks_field = pl.get('tracks', [])
        tid_list = [{'id': t['id']} for t in tracks_field]
    
    all_ids = [t['id'] for t in tid_list]
    total = pl.get('trackCount', len(all_ids))
    
    print(f"「{name}」trackCount={total}  拿到 {len(all_ids)} 个 ID\n")
    
    if len(all_ids) <= 10 and total > len(all_ids):
        print("  ⚠ 只拿到首页 ID，尝试分页拉取...")
        # 用 track/all 分页
        for offset in range(0, total, 200):
            data2 = weapi({'id': pid, 'limit': 200, 'offset': offset, 'csrf_token': '',
                          'total': 'true', 'n': 1000})
            try:
                r2 = sess.post('https://music.163.com/weapi/v6/playlist/detail',
                              data=data2, headers=HEADERS, timeout=20)
                d2 = r2.json()
                pl2 = d2.get('playlist', d2)
                tracks2 = pl2.get('tracks', [])
                for t in tracks2:
                    tid = t.get('id')
                    if tid and tid not in all_ids:
                        all_ids.append(tid)
                if not tracks2:
                    break
                print(f"  offset={offset} → 累计 {len(all_ids)} IDs")
            except Exception as e:
                print(f"  offset={offset} 失败: {e}")
                break
            time.sleep(0.5)
        
        if len(all_ids) <= 10:
            print("\n❌ 无法获取完整 ID 列表，可能是私有歌单")
            print("  尝试用您已有的10首导出...")
    
    print(f"  共 {len(all_ids)} 首\n")
    
    # 获取歌曲详情
    all_tracks = []
    for i in range(0, len(all_ids), 300):
        batch = all_ids[i:i+300]
        c = json.dumps([{'id': str(sid)} for sid in batch])
        data3 = weapi({'c': c, 'csrf_token': ''})
        try:
            r3 = sess.post('https://music.163.com/weapi/v3/song/detail',
                          data=data3, headers=HEADERS, timeout=30)
            d3 = r3.json()
            songs = d3.get('songs', [])
            if not songs:
                # 降级：逐首
                for sid in batch:
                    c2 = json.dumps([{'id': str(sid)}])
                    d4 = weapi({'c': c2, 'csrf_token': ''})
                    r4 = sess.post('https://music.163.com/weapi/v3/song/detail',
                                  data=d4, headers=HEADERS, timeout=15)
                    d5 = r4.json()
                    songs2 = d5.get('songs', [])
                    all_tracks.extend(songs2)
            else:
                all_tracks.extend(songs)
        except Exception as e:
            print(f"  批次 {i//300+1} 异常: {e}")
        
        print(f"  {len(all_tracks)}/{len(all_ids)}")
        time.sleep(0.2)
    
    if not all_tracks:
        print("❌ 没获取到歌曲"); input(); return
    
    # 输出
    print(f"\n{'='*50}")
    print(f"歌单: {name}  |  共 {len(all_tracks)} 首")
    print(f"{'='*50}\n")
    for i, t in enumerate(all_tracks[:30], 1):
        song = t.get('name', '?')
        artists = ' / '.join(a.get('name','') for a in t.get('ar', []))
        print(f"{i:3d}. {song}  —  {artists}")
    if len(all_tracks) > 30:
        print(f"    ... 还有 {len(all_tracks)-30} 首")
    
    fname = re.sub(r'[\\/*?:"<>|]', '_', name) + '.csv'
    with open(fname, 'w', encoding='utf-8-sig', newline='') as f:
        w = csv.writer(f)
        w.writerow(['序号','歌曲','歌手','专辑','时长'])
        for i, t in enumerate(all_tracks, 1):
            song = t.get('name', '未知')
            ar = t.get('ar') or []
            artists = ' / '.join(a.get('name','') for a in ar)
            al = t.get('al', {}) or {}
            album = al.get('name','') if isinstance(al, dict) else ''
            dt = t.get('dt', 0) or 0
            m, s = divmod(int(dt)//1000, 60)
            w.writerow([i, song, artists, album, f'{m}:{s:02d}'])
    
    print(f"\n✅ 已导出 {len(all_tracks)} 首 → {fname}")
    input("\n按回车退出...")

if __name__ == '__main__':
    main()

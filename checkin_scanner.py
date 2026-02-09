from pynput import keyboard
import requests
import time

# 1. 確保 API 網址正確
API_URL = "http://localhost/attendance_system/api/checkin_handler.php"

# 暫存變數
current_uid = ""
last_key_time = 0

def on_press(key):
    global current_uid, last_key_time
    try:
        now = time.time()
        
        # 處理數字鍵
        if hasattr(key, 'char') and key.char is not None:
            # 智慧過濾：如果兩個按鍵間隔超過 0.1 秒，代表是人手打字，直接清空重來
            if now - last_key_time > 0.1:
                current_uid = ""
            
            if key.char.isdigit():
                current_uid += key.char
            
            last_key_time = now

        # 讀卡機通常以 Enter 結尾
        elif key == keyboard.Key.enter:
            if current_uid and len(current_uid) >= 8: # 確保卡號長度足夠
                send_to_system(current_uid)
            current_uid = "" # 送出後務必清空
                
    except Exception as e:
        pass

def send_to_system(uid):
    # 這裡的 print 會出現在背景終端機，但不需要點開它
    print(f"\n📡 偵測到真實卡號: {uid}，傳送中...")
    try:
        response = requests.get(API_URL, params={'uid': uid}, timeout=10)
        if response.status_code == 200:
            res = response.json()
            if res.get('success'):
                # 簽到成功訊息
                print(f"✅ 簽到成功: {res['data']['name']} (第 {res['data']['count']} 堂課) - {res['message']}")
            else:
                print(f"❌ 失敗: {res.get('message')}")
        else:
            print(f"⚠️ 伺服器異常: {response.status_code}")
    except Exception as e:
        print(f"🚨 連線錯誤: {e}")

# 啟動宣告
print("🏓 全域背景監控已啟動")
print("提示：現在你可以縮小此視窗，直接去操作 admin_dashboard.php。")
print("感應卡片時，儀表板會自動更新，不需要點回這裡。")

with keyboard.Listener(on_press=on_press) as listener:
    listener.join()
# TideBites PR 2 管理員系統

日期：2026-11-26（Asia/Taipei）。本次基底為最新 `origin/main`：`9bd0eac2f7ed8402c923edcbe802fdf84b5fc965`，分支 `feature/phase2-admin-system`。

PR #66 已在 2026-11-26 合併；其提交 `e6599a300be64dfb1ba30857aa3ae1c44d979832` 是 main 的祖先，PR 1 的資料庫、PDO、工具及文件內容均相同。本次沒有修改 SQL、共用 PDO、帳號權限或候選資料合約。B 尚未正式確認候選合約；合併 PR 1 不表示那些待確認項目已獲 B 同意。

## 功能與範圍

- 管理員登入、POST 登出、伺服器端權限控制、Session 與 CSRF 防護。
- Food Management：真實 MySQL 餐點列表、圖片、摘要、價格、分類、排序、上下架狀態與統計。
- 名稱／描述搜尋、分類及狀態篩選。
- 新增、編輯、上下架與真正刪除餐點。
- JPG/JPEG、PNG、WebP 圖片上傳、預覽、更換及保守清理。
- 唯讀 Orders：搜尋、每頁 20 筆、訂單詳情、歷史餐點名稱／價格快照與台北時間。
- 獨立後台 CSS/JS，以 TideBites Control Deck 呈現海洋藍、珊瑚橘、黃色及薄荷綠；重用海浪 Logo、海豚、海草、潛水艇。前台與後台統一只用 Lato 一種字體（已移除 Raleway、Alex Brush 手寫體），Google Fonts 無法連線時使用系統 fallback（Segoe UI／Arial）；介面為英文。
- 375、1024、1366px 響應式介面、鍵盤焦點與 label、無 JavaScript 時可使用基本表單及導覽。

不含註冊、忘記密碼、角色、顧客會員、訂單修改／刪除／狀態、付款或營收分析。依本次授權，`index.html` 只在 Footer 的 EXPLORE 區新增 Admin Login 相對連結；Popular Tags 與原內容保留。未修改顧客端 `css/`、`js/`、`assets/`、README 或 B 的分支，未增刪正式餐點。

## 本機啟動

先進入 clone 的專案根目錄，即同時包含 `index.html`、`admin/`、`database/` 的資料夾。不要以專案父目錄當 document root。

```powershell
.\start.bat
```

也可直接執行：

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\start.ps1
```

腳本使用 PATH 中的 PHP，確認指定埠可用、啟動 server 並取得首頁 HTTP 200 後印出網址，不操作桌面或自動開瀏覽器。不安裝、不匯入、不重設資料庫，不含密碼或個人絕對路徑。`Bypass` 只作用於這次程序。

| 入口 | 預設網址 |
| --- | --- |
| 網站首頁 | `http://127.0.0.1:8000/index.html` |
| Admin Login | `http://127.0.0.1:8000/admin/login.php` |
| 管理員入口 | `http://127.0.0.1:8000/admin/` |
| Food Management | `http://127.0.0.1:8000/admin/foods/index.php` |
| Orders | `http://127.0.0.1:8000/admin/orders/index.php` |

也可從首頁 Footer → EXPLORE → Admin Login 進入。連結為 `admin/login.php`，可跟隨網站部署子目錄解析。

保留終端機，完成時在該終端按 Ctrl+C，腳本的 finally 會關閉它建立的 PHP 子程序。若強制終止整個終端程序，應檢查該次 PHP server 是否仍占用埠。

不同 PHP 路徑或埠：

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\start.ps1 -Php '<php.exe 完整路徑>' -Port 8001
```

手動等價啟動指令：

```powershell
php -d display_errors=0 -d log_errors=0 -S 127.0.0.1:8000 -t . router.php
```

**PR 2 起要帶 `router.php`。** PR 1 文件中沒有 router 的舊指令不提供完整靜態檔案保護。PHP 內建伺服器只供本機開發；本次沒有安裝或設定正式 hosting。

## 安全初始化第一位管理員

沿用 PR 1 的外部設定、`config/database.php` 與 `tidebites_pdo()`。正式本機資料庫已存在時，只需 Check，**不要再 Install**。

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\database\manage.ps1 -Action Check
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\database\manage.ps1 -Action CreateAdmin
```

在自己的終端輸入 username，再兩次隱藏輸入新密碼。username 為 3–100 個 ASCII 英數字或 `._-`；密碼 12–72 bytes。這是網站管理員，與 MySQL app 帳號不同。不提供預設帳密，不在聊天輸入密碼，不把密碼寫成指令參數。同名帳號會被拒絕，不會重設既有密碼。

`CreateAdmin` 的 PR 1 成功訊息仍寫「Login/access control belongs to PR 2」；這是歷史文字，本分支已實作登入，不需要改動既有 CLI。若已有管理員，直接登入即可。

真實資料庫設定預設仍位於 `../tidebites-local/database.php`，也可由 `TIDEBITES_CONFIG` 指向 repo 與 document root 外的設定。工具的 `-ConfigPath` 只作用於該工具子程序；要讓網站使用自訂設定，須在啟動 server 的終端設定同一個 `TIDEBITES_CONFIG`。

## 日常操作

1. 在 Admin Login 輸入管理員 username/password。成功後進入 Food Management。
2. 使用 Search food、Category、Status 後按 Apply Filters；Reset 清除篩選。排序依 `sort_order,id`。
3. 按 Add Food Item，填寫 Food Name、Description、Price、Category、Food Image、Image Alt Text、Display Order、Availability Status，按 Save Food Item。
4. 按 Edit 載入原資料；可修改所有可編輯欄位。圖片欄留白會保留原圖，選擇新檔則顯示預覽；Save Changes 儲存，Cancel 返回。
5. 按 Deactivate 只將 `is_active` 設為 0，餐點仍存在；Activate 可恢復為 1。也可在編輯表單選擇狀態。
6. 按 Delete 先進入確認頁。可選 Deactivate Instead，或勾選永久刪除確認後按 Delete Food Item。GET 不會刪資料。
7. 導覽的 Orders 可查閱訂單，Food Management 返回餐點管理；手機用 Menu 展開相同導覽。
8. Logout 以 CSRF 保護的 POST 清除管理員 Session/Cookie。Return to Website 返回原首頁。

成功的寫入採 POST → 303 → GET；重新整理列表不重送表單。新增另有綁定 Session 的一次性表單 token，成功後失效，重放回 409。每個 Session 最多保留 20 張新增表單、期限一小時；過期時重新開啟表單。

表單失敗會保留可安全回填的文字並標示錯誤；瀏覽器不能安全回填檔案選取，需重新選圖。不存在／已刪除的 ID 回 404。讀取或更新服務暫時故障會顯示一般英文訊息，不顯示 SQL、帳密、雜湊或堆疊。

## 訂單查閱

- 僅允許已登入管理員透過 GET 讀取，沿用同一 Session、授權與 `tidebites_pdo()`；POST 回 405，沒有修改訂單端點。
- 列表依 `created_at DESC,id DESC`，每頁 20 筆，可用編號、姓名、電話或外送地點綜合搜尋。同一數字也可能命中電話／姓名，編號搜尋不是獨立的精確篩選。
- View Details 顯示顧客資料、取餐時間（台北時間）、備註、餐點名稱、單價、數量、行小計、Subtotal、Student Discount、Total。金額以整數分計算行小計，訂單總額直接讀取已保存值，沒有重新套用折扣政策。
- 學生折扣為顧客自選：`discount_amount > 0` 即代表顧客勾選「取餐時出示學生證」，明細的 Student ID 欄會提醒店家取餐時核對訂購人學生證；折扣為 0 則顯示不需核對。未新增資料表欄位。
- 餐點名稱與單價只取 `order_items.food_name/unit_price`，不 join 當前 `food_items`；餐點改價或刪除不改歷史畫面。
- 將資料庫 UTC 明確轉為 Asia/Taipei（UTC+08:00）。姓名／電話為 NULL 或空白時顯示 Not provided；空備註顯示 No notes provided。
- 無訂單顯示潛水艇主題 No orders yet；無搜尋結果顯示 No matching orders；非法或不存在 ID 回 404。
- 顧客送單仍由 B 實作。本版不產生正式訂單、不變更 schema 或 app 權限。

## Control Deck 視覺與可用性

`admin.css` 保留既有佈局與互動基礎，`deck.css` 集中品牌視覺覆寫，沒有載入顧客端 CSS/JS。登入使用海洋雙欄／手機直排、海豚與海草；管理頁有品牌海浪色帶、彩色統計及卡片；表單以藍／珊瑚／薄荷色區分內容、圖片與上下架；刪除頁維持明確危險色及確認步驟。訂單卡片／詳情在手機改為單欄。

裝飾只有輕量海豚浮動，尊重 `prefers-reduced-motion`；不播放音樂、不載入首頁動畫。導覽保留鍵盤焦點、手機 Escape 關閉與無 JS 可用性。CSP 僅額外允許 Google Fonts 的樣式／字型來源，沒有放寬腳本來源。

## 欄位規則

| 欄位 | 管理員輸入規則 |
| --- | --- |
| name | 非空白，最多 150 個 Unicode 字元 |
| description | 非空白，最多 65,535 UTF-8 bytes，符合 TEXT 容量 |
| price | 0–99,999,999 整元；接受原 PDO 的 `.00`，拒絕負數、科學記號與非零小數 |
| category | 僅 `meals` / `desserts`，介面顯示 Meals / Desserts |
| image_alt | 管理員表單要求 1–500 字元，以提供圖片替代文字；沒有更改 DB 欄位約束 |
| sort_order | 整數 0–65,535；同序依 ID |
| is_active | 僅 0/1 |
| id | 由 MySQL 自增，不提供修改介面 |

全數在 PHP 驗證；HTML/JS 只補強操作體驗。資料庫金額仍為 `DECIMAL(10,2)` 的新臺幣元字串。整元限制是本管理介面暫時沿用的現有政策，**不是 B 已同意的新合約**。若未來共同決定允許角分，需同步確認 B 的折扣與金額算法，再調整表單及測試。

## 圖片上傳及生命週期

- 本次限制 2 MiB（2,097,152 bytes），相容目前 `upload_max_filesize=2M`、`post_max_size=8M`。
- 實際 MIME 使用 `finfo`；再比對副檔名、`getimagesize` 的圖片型態與尺寸、格式完整性邊界（PNG chunks/CRC/IEND、JPEG SOI/EOI、WebP RIFF 長度）。不信任 browser 宣告的 MIME。
- 最大單邊 6,000 px，總像素不超過 16,000,000。不接受 SVG、GIF、HTML、PHP 等其他格式，不接受原檔名中的路徑分隔、`..` 或控制字元。
- 只接受 HTTP 上傳的暫存檔，以 `random_bytes(16)` 產生 `tb-<32 hex>.jpg/png/webp`；採獨占建檔，原始名稱不成為儲存路徑。
- 儲存於 `uploads/foods/`，DB 只保存如 `uploads/foods/tb-....png` 的專案根目錄相對路徑。原有 `assets/images/` 不移動、不覆寫、不刪除。
- 更換時先驗證並儲存新檔，再以 transaction / row lock 寫 MySQL。資料庫失敗時 rollback 並清理新檔；原資料與原圖保留。
- 資料庫 commit 後才清理舊圖。清理只接受精確的管理員生成檔名，排除 symlink，並先查 `food_items.image_path` 的引用數。仍被引用的檔案保留；不能確認引用時寧可保留。
- 若舊圖移除失敗，已成功的 DB 更新不回滾，介面會提示維護者檢查未使用圖片。不要直接刪整個 uploads 資料夾；先比對 DB 引用、備份，再只處理明確未被引用的生成檔。
- router 對 uploads 使用固定 image MIME、`nosniff` 與 `readfile()`，不交由 PHP 執行；只接受合法生成路徑及 GET/HEAD。即使圖片 metadata 包含 PHP 外觀字串也只會當圖檔回傳。

本版不要求 GD、不重新編碼、不移除 EXIF、不提供裁切或病毒掃描。格式檢查不是完整影像解碼器或惡意內容掃描；公開部署前可另評估重新編碼及檔案掃描。請使用 C 已確認來源的正式餐點圖片。

目前不提高上限。未來若共同決定 5 MiB，需一起調整 PHP `upload_max_filesize=5M`、較大的 `post_max_size`（例如 8M）、PHP 的 `ADMIN_IMAGE_MAX_BYTES`、JS 限制、提示與測試；可透過啟動時 `php -d` 針對程序設定，不必修改整台機器的 php.ini。單改 PHP ini 不會放寬本版 2 MiB 應用限制。

## 安全性設計

- 全部 admin 頁面與修改端點共用 bootstrap/auth，包含圖片處理；未登入 GET 303 至登入頁、未登入寫入 401。
- 指定 HTTP methods，錯誤 method 回 405/Allow；缺少或錯誤 CSRF 回 403；欄位錯誤 422；過大 POST 413。
- PDO 原生 prepared statements；搜尋的 `%` / `_` 當文字，未把搜尋條件拼成 SQL。輸出以 `htmlspecialchars` 轉義，圖片 URL 只允許本地圖片路徑。
- `password_verify()` 驗證既有 CLI 的雜湊。不存在帳號亦走 dummy bcrypt 驗證；失敗訊息不揭露帳號是否存在。
- 啟用 strict session mode、only cookies、HttpOnly、SameSite=Strict、session cookie lifetime=0；cookie 路徑限定 admin。HTTPS 時 Secure=true，本機 HTTP 時 false。不信任任意 `X-Forwarded-*` header。
- 登入成功重新產生 Session ID 並銷毀舊 Session；每次授權查 admins，驗證 ID 與憑證指紋。閒置 30 分鐘或登入滿 8 小時須重登；Session 欄位異常、帳號不存在或雜湊改變均失效。
- 不新增 admins UPDATE 權限，所以本版不做自動密碼 rehash、重設或角色管理。
- 登入節流：每個大小寫正規化 username 15 分鐘最多 5 次、每個 `REMOTE_ADDR` 15 分鐘最多 30 次。包含成功、失敗與格式不合法的嘗試；不靠 cookie，換 Session 不能重置。回 429/Retry-After，等待 15 分鐘再試。記錄只存 hash key / count / 到期時間，以檔案鎖原子更新；過期資料移除，最多 10,000 個有效 bucket，無法讀寫時拒絕登入。
- Session 與節流檔放在外部設定旁的 `admin-runtime-<checkout hash>/`，不在 repo/document root。可用伺服器環境變數 `TIDEBITES_ADMIN_RUNTIME` 指向另一個外部資料夾。不得放進 ZIP 或 Git；Windows ACL 應限制為執行 PHP 的本機使用者。
- 後台回應 `Cache-Control: no-store`、CSP、`X-Frame-Options: DENY`、`nosniff`。不載入顧客端動畫／音訊。伺服器錯誤不顯示或記錄原始 PDO 例外、表單內容或密碼。

## HTTP 內部檔案保護

`router.php` 為 PHP 內建 server 的輕量路由：

- 封鎖 `database/`、`config/`、`docs/`、`tests/`、`scripts/`、`admin/includes/`、dotfiles、啟動腳本及 router 本身。
- 驗證 URL 與解析後的實體路徑，拒絕編碼分隔符、重複編碼、點路徑、Windows ADS/尾端點與空白等變體，不使用未知路徑回退至 index 的行為。
- 保留 `/`、`index.html`、現有 CSS/JS、圖片、圖示與音訊。
- 公開 PHP 檔必須實際存在；`admin/` 僅允許列出的頁面。未來 B 的實際 API PHP 檔可正常執行，不需改 admin 白名單。
- 原 PR 1 PHP 內的直接 HTTP guard 保持不變。

`.htaccess` 另提供 Apache 2.4 對應的目錄封鎖、禁止 listing 與上傳目錄靜態 handler；需要 Apache 啟用相關 `AllowOverride` / rewrite / authz 支援。**.htaccess 不作用於 PHP 內建 server。** 本次實測 router；Apache/IIS/Nginx/HTTPS 部署未實測，必須在實際伺服器確認相同保護，不能假設放入檔案就生效。

## 檔案結構

```text
admin/
  index.php                   授權後轉往管理列表
  login.php / logout.php      登入與受保護的 POST 登出
  foods/
    index.php                 查詢、搜尋、篩選、統計
    create.php / edit.php     表單與 POST 處理
    status.php                POST 上下架
    delete.php                GET 確認頁 / POST 真正刪除
  orders/
    index.php                 唯讀訂單搜尋、分頁與空資料畫面
    detail.php                歷史快照、送餐資訊與金額
  includes/
    bootstrap.php             共用設定、回應標頭、錯誤、Session 初始化
    auth.php / csrf.php       授權、登入節流、CSRF、一次性新增表單
    validation.php            欄位與 ID 驗證
    upload.php / foods.php    圖片生命週期、交易式資料寫入
    orders.php                訂單 ID、UTC 時間與金額格式
    header.php / footer.php / food-form.php
  assets/css/admin.css
  assets/css/deck.css          Control Deck 獨立品牌視覺
  assets/js/admin.js
uploads/foods/.gitkeep / .htaccess
router.php / .htaccess         開發與 Apache HTTP 保護
start.bat / start.ps1          Windows 本機啟動
tests/
  admin-fixture.php            CLI-only 隔離 fixture，拒絕操作正式 DB
  run-admin-tests.py           隔離 MySQL、HTTP、安全性、回歸與清理
  admin-browser.cjs            headless Chrome 三尺寸操作及截圖
  test-launcher.ps1            啟動就緒、router 與停止清理測試
  artifacts/                  本機測試報告與截圖，Git 忽略
docs/admin-system.md / admin-testing.md
```

## 與 B 的共用介面及交接

1. 原四張資料表、欄位、外鍵、價格單位、ID、UTC 與 PDO 介面完全不變。恢復工作後再次 fetch，B 的 `origin/phase2-client-side` 為 `2eb1a5a`（合併 PR #68），與 `origin/main` 沒有檔案內容差異；沒有修改或合併該分支。
2. 管理員讀取 admins、CRUD food_items，並唯讀 orders / order_items。正式程式不寫 orders / order_items；刪除餐點由原 FK `ON DELETE SET NULL` 保留歷史名稱／單價快照。訂單查閱沒有要求擴大 DB 帳號權限。
3. B 的動態菜單繼續 `WHERE is_active=1 ORDER BY sort_order,id`；詳情及送單要重新驗證餐點存在、上架與價格。改名／改價不更新既有訂單。
4. `image_path` 同時可能是 `assets/images/...` 或 `uploads/foods/...`，一律相對專案根，勿相對 API 的子目錄解析。管理員不提供外部圖片 URL。
5. **需要 B 調整的啟動命令**：使用 `start.bat` 或 `php ... -t . router.php`。這是新增的 HTTP 保護方式，不是資料合約變更。B 的新 API 不應放在被封鎖的內部目錄。
6. ~~`index.html` 仍是 Phase I 靜態入口~~ 已完成：B 的 `api/foods/list.php` 動態讀取菜單，管理員上下架／改價後重新整理首頁即反映；下單由 `api/orders/place.php` 寫入，後台 Orders 可看到。
7. B 尚未回覆的候選事項（整元／折扣、ID 處理、姓名電話、送單鎖定等）仍以 `database-contract.md` 的未確認清單為準。本次沒有將它們改成已確認。
8. **本次唯一顧客端修改**：`index.html` Footer 的 EXPLORE 區加入 `<p><a href="admin/login.php">Admin Login</a></p>`。B 後續整合首頁時需保留這一行，避免覆蓋入口；Popular Tags、菜單、音樂及動畫程式完全不變。
9. B 完成真實送單後，需共同驗證寫入快照、NULL 顧客欄位、UTC 與總額在 Orders 的呈現。本次只用隔離 fixture，不能視為 B 送單整合已通過。

## 給 C 的最終 readme.txt 與課程 ZIP 資訊

可依下段整理最終英文操作說明；本次不覆寫 README 或其他組員檔案：

> Start the local PHP server from the project root using start.bat. Open http://127.0.0.1:8000/index.html in Chrome. To obtain administrator access, run database/manage.ps1 with -Action CreateAdmin in a local PowerShell terminal and enter a new username and password securely. Select Admin Login in the homepage footer, or open /admin/login.php. Use Add Food Item or Edit to manage details and images, Activate/Deactivate to change availability, and Delete followed by confirmation to permanently remove an item. Select Orders to search and view saved order details; orders cannot be edited here. Use Logout when finished. No default administrator account is included.

正式餐點影像是資料，**Git 不會因後台上傳而自動同步**。本版忽略 `uploads/foods/*`，防止測試圖、私有圖或未審核檔案誤提交。待 C 提供正式圖片及文案後，由 A/C：

1. 確認圖片來源／使用權、餐點內容及資料庫紀錄。
2. 為最終課程版本備份／匯出選定的正式資料，包含那些紀錄的相對 `image_path`；不要用 bootstrap 重跑來覆蓋資料。
3. 對需要納入版本庫的每張已確認圖片，明確 `git add -f -- uploads/foods/<精確檔名>`（本次沒有執行）。不要直接強制加入整個 uploads。
4. ZIP 必須同時含 SQL 與該 SQL 引用的全部圖片。若用 git archive 或只打包 tracked 檔案，先確認已審核的圖檔確實納入；不要只在 A 的本機存在。
5. 不納入外部個人設定、Session、節流檔、測試報告／暫存 DB 或個人密碼。課程驗收專用帳密與交付方式仍依 `database-setup.md` 的 C/D 交接說明，由小組自行設定，勿公開個人 root 密碼。

尚缺 C 的另外 10 道正式餐點；沒有生成假餐點補足教授至少 20 道的要求。最終 readme.txt、SQL 內容封存、英文 slides、影片與 ZIP 仍由 C/D 整理。

## 已知限制與驗收界線

- 本次為單機 PHP/MySQL 管理員系統。節流與 Session 使用本機檔案，未支援多節點共用儲存或受信任代理設定。
- 同時編輯同一餐點採 row lock 保證交易完整，文字欄位以最後成功儲存為準，沒有版本衝突提示；管理員應協調同時編輯。
- 檔案系統與 MySQL 無法形成同一原子交易；可處理一般失敗與回滾，但程序在寫入與 commit 之間被強制終止時可能留下孤兒圖，需由維護者核對引用後清理。
- 餐點列表依課程規模設計，未分頁；訂單列表每頁 20 筆、固定新到舊排序，沒有銷售分析或訂單寫入介面。
- 自訂檔案選取控制的文字為英文；作業系統原生檔案對話框及瀏覽器原生驗證訊息由使用者系統語言決定。
- 實際測試、清理及尚未驗證事項見 [admin-testing.md](admin-testing.md)。READY FOR REVIEW 指 A 的本次範圍可審查，不表示 B 整合或整個 Phase II 已完成。

技術參考：[PHP Session 安全設定](https://www.php.net/manual/en/session.security.ini.php)、[PHP 檔案上傳](https://www.php.net/manual/en/features.file-upload.post-method.php)、[getimagesize 的用途與限制](https://www.php.net/manual/en/function.getimagesize.php)。

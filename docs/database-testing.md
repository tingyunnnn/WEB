# TideBites PR 1 測試紀錄

日期：2026-11-25（Asia/Taipei）。分支：`feature/phase2-database`，基底：`origin/main` `75cbb4ce30980d46fe683310f953879d528d07df`。
PHP 8.4.28 x64、MySQL Community Server 8.4.9、Windows。

## 測試範圍與證據界線

這是本次**新增實作**的驗證，不是重跑完整 Phase 0。
MySQL84 服務確認 Running／自動啟動；原服務、3306 資料及個人 root 密碼均未變更。
為避免要求使用者提供 root 密碼，測試使用現有 mysqld 執行檔，在 repo 外的新暫存目錄建立獨立實例；沒有安裝另一份軟體或建立 Windows Service。

隔離實例先禁用 TCP，只開啟具唯一名稱的 Windows shared memory：
原生 mysql client 匯入**原檔** `database/tidebites.sql`、驗證 10 筆、刪除該 bootstrap DB，再經 stdin 設定隨機測試 root 密碼並停服。
接著以不同於 3306 的隨機 127.0.0.1 埠啟動，執行本 PR 的 `database/manage.php test`、Install、Check、CreateAdmin。
憑證僅在記憶體／匿名 stdin pipe 傳遞；產生的 app 設定檔位於 repo／HTTP 根目錄外，完成後刪除。
隔離實例使用較小的 buffer pool、關閉 performance_schema／binlog 以降低本機負擔；這不是負載或正式部署測試。

## 已實測通過

`Test` 的 **59 項**程式化檢查均通過（原 50 項 + PR 1 審查修正後新增 9 項）；另有原生 Client、CLI 全流程、路徑邊界與 HTTP 回歸檢查。

| 項目 | 實際方式／結果 | 狀態 |
| --- | --- | --- |
| SQL 可直接匯入 | 原生 MySQL 8.4.9 client 讀取 SQL 原檔，成功建立四表與 10 筆餐點 | 通過 |
| SQL PDO 匯入 | CLI 逐 statement 執行同一 SQL，沒有多 statements PDO 執行權限 | 通過 |
| 表／主鍵／外鍵 | information_schema 確認四表、4 個 PK、2 個 FK、InnoDB／utf8mb4_0900_ai_ci | 通過 |
| HTML UTF-8 解析與 Seed 保真 | 以獨立 fixture `database/seed-fixture.php`（自 Phase I `75cbb4c:index.html` 提取，與動態前端解耦）逐欄比對 id/name/description/price/category/image_path/image_alt/sort_order，並保留 UTF-8 DOMDocument 解析檢查 | 通過 |
| Seed 數量／圖片 | 10 筆，meals 7／desserts 3；10 個原餐點圖片路徑均存在 | 通過 |
| 查詢順序／下架 | 各分類按 sort_order,id 與原 HTML 順序一致；is_active=0 不出現在有效菜單 | 通過 |
| 重複初始化與清空邊界 | 重複匯入不重複；編輯與部分刪除（刪 id=10 後剩 9 筆）均保留；全部清空為 0 筆時重跑 bootstrap 會重新填入 10 筆（符合文件與 SQL 註解規格） | 通過 |
| 保護既有資源 | 同名 DB、既有帳號、既有設定檔均拒絕接管／覆寫 | 通過 |
| 共用 PDO | 由外部設定經 tidebites_pdo() 使用測試 app 帳號登入；UTF-8、UTC、原生 prepare 設定正確 | 通過 |
| 餐點 CRUD | 僅在隨機測試 DB 內增、讀、改、刪新餐點，不修改正式種子 | 通過 |
| 字元與 prepared statements | 中文／英文／emoji、單引號、SQL 外觀字串作為參數，逐字讀回；不是執行 SQL | 通過 |
| 密碼特殊字元與輸入邊界 | 隨機測試 app 密碼含中文、單引號與反斜線可正常建立與登入；拒絕含 NUL 位元組之 app 密碼、空白／前後空白之 `root` 帳號 | 通過 |
| 金額／資料約束 | 拒絕負價格、空白名稱、錯誤分類、重複 PK、不合理總額／折扣、空白地點、quantity=0／100 | 通過 |
| 免會員訂單 | 不需 customer_id；姓名／電話為 NULL 的候選訂單及明細可在同一交易提交 | 通過 |
| FK 與交易回滾 | 不存在訂單／餐點的 FK 失敗；明細失敗後整張測試訂單 rollback | 通過 |
| 歷史名稱／價格 | 改菜單名稱與價格後，明細快照不變 | 通過 |
| 刪除餐點 | 明細 food_item_id 設 NULL，名稱／單價與訂單總額保留 | 通過 |
| 明確刪除訂單 | 測試管理員明確刪訂單後明細 CASCADE；不是餐點刪除造成的 cascade | 通過 |
| 管理員初始化 | 無預設帳號；password_hash／password_verify 往返、大小寫不敏感唯一 username 均通過 | 通過 |
| 帳號最小權限 | app 不能讀其他測試 DB 或 mysql.user、不能 DDL、不能 UPDATE／DELETE `admins`／`orders`／`order_items`、不能 CREATE USER | 通過 |
| 連線錯誤保護 | 錯誤密碼時只拋一般訊息，無底層 previous exception、密碼或完整 DSN；網站連線拒用 root（含前後空白與大小寫） | 通過 |
| 本機設定保護與目錄回滾 | 拒絕 repo 內路徑、不存在子目錄與 `..` 路徑遍歷且不在 repo 殘留空目錄；安裝失敗僅回滾本次新建空目錄，不刪除原有目錄與檔案 | 通過 |
| CLI 與 PowerShell `-ConfigPath` 流程 | 真正執行 Install → Check（含 `manage.ps1` 絕對與相對 `-ConfigPath` 在 `$PWD` 與 `.NET CurrentDirectory` 不同時皆正確解析）→ CreateAdmin → 重複管理員與再次 Install 拒絕 | 通過 |
| HTTP 內部入口 | config/database.php、database.example.php、manage.php、tools.php、tests.php 及 manage.php/install（含 Windows 大小寫變體）皆回 404 | 通過 |
| HTTP 一般錯誤 | 臨時測試端點捕捉連線例外，回 503 一般訊息，無密碼／設定路徑／堆疊 | 通過 |
| 原網站 HTTP | index.html、CSS、JS、52 個圖片／圖示共 55 個 GET 都 200，SHA-256 與磁碟相同 | 通過 |
| 音訊 HTTP | 兩個音訊 HEAD 都 200，Content-Length 正確；未播放音效或音樂 | 通過 |
| Phase I 不變 | git diff origin/main -- index.html css js assets README.md docs/TideBites_Web_Content_Package.md 為空 | 通過 |
| 語法與差異檢查 | 所有新增 PHP 經 php -l、PowerShell AST 語法檢查、git diff --check | 通過 |
| 清理 | 測試 DB／帳號刪除後再次查系統表確認不存在；外部測試設定刪除；MySQL／PHP 測試程序停止，暫存實例刪除 | 通過 |

兩個音訊的實際大小：backgroundmusic.mp3 為 12,467,865 bytes；bubble.mp3 為 31,242 bytes。
此處只驗證檔案與 HTTP，不聲稱重新驗證 Chrome 的播放政策或互動體驗。

> 後續變更（2026-11-28）：網站已移除開場畫面、背景音樂與點擊音效，`assets/audio/` 兩個檔案已刪除。上表「音訊 HTTP」為當時的歷史紀錄，目前不再適用。

## 已處理的測試發現／目前失敗項目

- 第一輪 HTTP 測試原先假設所有不存在的外部設定路徑一定回 404；實際 PHP 內建 server 會將部分未知路徑回退至 `index.html`（200）。已調整測試以驗證 200 回應逐字等於首頁且不含測試密文字串；實際內部 PHP 檔皆回 404。
- PR 1 審查修正：已修復 `database/manage.php` 在路徑驗證前先行 `mkdir` 的問題、`database/manage.ps1` 的 `-ConfigPath` 相對路徑解析問題、`docs/database-setup.md` 的個人路徑範例、`tb_provision()` 的 NUL byte 驗證、`tidebites_connect()` 的空白 `root` 檢查，並補足 UTF-8 DOMDocument 解析與 `admins`/`orders`/`order_items` 的 `UPDATE`/`DELETE` 權限拒絕測試。
- 動態菜單相容性修正（`fix/phase2-database-regression-tests`）：原先 `tb_original_menu()` 直接從 `index.html` 解析靜態 `<article>`，在 B 改為 API 動態菜單後會出現 `No original menu data found.`。已自 Phase I 原始版本 `75cbb4ce30980d46fe683310f953879d528d07df:index.html`（Git blob `d288ae3d620f62e522a20c86e29e99e058b90960`）建立獨立 fixture `database/seed-fixture.php`（含 CLI-only 404 防護），保留 59 項檢查與原有嚴格度，且不從 SQL／API／資料庫反向產生預期答案。
- 備註：`database/`、`config/`、`docs/` 中不含密碼的非 PHP 靜態檔案（如 `.sql`、`.ps1`）在 PHP 內建開發伺服器 `-t .` 下仍為靜態可讀；PR 2 的 `router.php` 與 `.htaccess` 已對 `database/` 進行封鎖。

**目前沒有尚未解決的已執行測試失敗項目。**

## 尚未執行／不屬於本次驗證

| 項目 | 狀態與原因 |
| --- | --- |
| 在使用者 MySQL84／3306 建立正式 tidebites／tidebites_app | 未執行，需要使用者本機輸入 root；已提供 Install，等價安裝流程已在隔離實例通過 |
| 使用者正式本機 app 設定與管理員帳號 | 未建立；不替使用者指定真實密碼 |
| macOS／Linux、Apache／IIS／正式 hosting | 未實測；文件提供差異，不宣稱全部部署平台通過 |
| Headless／GUI Chrome 的視覺及互動回歸 | 未重測；本次採 HTTP、資源雜湊與 Git 差異驗證，沒有操作實體鍵鼠 |
| 跨列總額重算、重複訂單、真實訂單提交 | B 後續工作；本次只測 schema 固定金額及交易，不是完成訂單系統 |
| 動態菜單、舊購物車遷移、API 錯誤處理 | B 後續工作 |
| 登入／登出、存取權限、CSRF、正式圖片上傳 | PR 2 後續工作；本 PR 只有 admins 結構及 CLI 雜湊初始化 |
| 至少 20 道正式餐點 | 尚未滿足，現有 10 道，待 C 提供另 10 道 |
| readme.txt、英文 slides／影片及課程 zip | C/D 後續交付，技術要求已整理至 database-setup.md |

## 重現方式與結論

在自己的 MySQL 8.4 服務上，以 PowerShell 執行：

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\database\manage.ps1 -Action Test
```

只在自己的終端隱藏輸入 root 密碼，不傳到聊天；測試使用隨機獨立資料庫並自動清除。
結果應為 PASS，59 個 checks 都 PASS，cleanup 無 CLEANUP_FAILED。
執行時需可讀 `database/seed-fixture.php`、圖片及 SQL；不再依賴 `index.html` 的靜態餐點卡片。未來擴充至 20 道正式餐點時，須由 A/C 依核定菜單規格分別更新 `database/tidebites.sql` 與 `database/seed-fixture.php`（以及管理員端測試中的預期筆數），切勿直接從 SQL 或資料庫傾印覆蓋 fixture。

**PR 1：READY FOR REVIEW。** 實作及本次必要資料庫測試已完成；本機正式安裝需使用者安全輸入密碼。
**尚不符合直接合併條件**：仍需使用者審查及 A/B 確認候選資料合約。沒有 commit、push、PR 或 merge。
這個結論不等於整個 Phase II 已完成；20 道餐點、B 的顧客端、PR 2 的管理員系統及 C/D 交付仍待後續工作。

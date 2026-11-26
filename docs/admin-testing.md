# TideBites PR 2 管理員系統測試紀錄

日期：2026-11-26（Asia/Taipei）。分支：`feature/phase2-admin-system`；基底：`origin/main` `9bd0eac2f7ed8402c923edcbe802fdf84b5fc965`。

## 最終結果與證據

**READY FOR REVIEW（A 的 PR 2 與本次 Control Deck／唯讀訂單範圍）。** 最後完整執行結果為 PASS，無尚未解決的已執行測試失敗。本輪因新增訂單頁、共用導覽與視覺調整，沿用既有測試並增加訂單及首頁入口案例；沒有重新開發登入、CRUD 或共用資料庫。

| 驗證層 | 實際結果 | 本機證據 |
| --- | --- | --- |
| 隔離 MySQL、HTTP、安全性、回歸及清理 | 245 個 checks 全部 PASS | `tests/artifacts/report.json` |
| Headless Chrome 操作與 RWD | 78 個 checks 全部 PASS，375/1024/1366px | `tests/artifacts/browser-report.json` |
| PR 1 原資料庫測試 | 59 個 checks 全部 PASS | 主報告的 `database_regression` |
| Windows 啟動腳本 | readiness / router_protection / server_stopped 全部 true | 主報告的 `launcher` |
| PHP / JavaScript / PowerShell 語法 | 通過 | php -l、node --check、PowerShell AST |
| 版本差異 | git diff --check 通過；顧客端僅 Footer 新增一行；共用 DB 無差異 | 最終 Git 檢查 |

245 個 checks 包含對瀏覽器與 PR 1 測試結果的彙總斷言，不能直接相加宣稱完全獨立的案例總數。報告與 21 張改版截圖保留在本機，`tests/artifacts/` 已被 Git 忽略，不含測試密碼、Session Cookie 或真實設定。先前版本的 219／51／59 是歷史結果，本輪數字以上表為準。

## 環境與隔離方式

- Windows、PHP 8.4.28 x64、MySQL Community Server 8.4.9、Headless Chrome 147.0.7727.138、Playwright；Python/Pillow 產生暫時圖片。
- 使用現有執行檔，沒有重新安裝軟體、建立 Windows Service 或操作實體鍵鼠。
- 正式 MySQL84 / 3306 僅供唯讀連線與基線快照。四張正式表前後 row count / SHA-256 摘要一致；以執行時現有資料為準，不假設正式餐點固定 10 筆。
- 系統 temp 下隨機 `tidebites-admin-qa-*` 目錄存放短暫 MySQL 實例。初始化時禁止 TCP，只開唯一 Windows shared memory；透過 stdin 設定隨機測試 root 密碼後關閉，再於獨立 127.0.0.1 埠啟動。
- 應用測試使用 `tbadmin...` 資料庫、`tbadmin_...@localhost` 最小權限帳號，沿用 PR 1 tb_provision、SQL 與共用 PDO，沒有另一套正式 schema。
- `qa_admin` 密碼每輪隨機產生。憑證透過記憶體/stdin 傳遞，外部設定、Session 與節流記錄不在 repo/document root。
- admin-fixture.php 僅 CLI 可用，資料操作先確認 DB 名符合 `tbadmin[0-9a-f]+`；故障注入、訂單 fixture、共用圖片引用及重設都只作用於隔離 DB。

## 實際測試案例

| 類別 | 已執行案例與結果 |
| --- | --- |
| 正確登入 | CLI 建立的管理員可登入，303 導向列表；登入前後 Session ID 不同 |
| 錯誤登入 | 空白、SQL 外觀 username、不存在帳號與錯誤密碼拒絕，顯示一般錯誤；登入需 CSRF |
| 登入節流 | 同一 username 達限制後回 429/Retry-After；換 Cookie 並改 username 大小寫仍受限制 |
| Cookie | HTTP 回應有 HttpOnly、SameSite=Strict、path=/admin/，本機 HTTP 未錯設 Secure |
| 授權 | 未登入不能讀管理入口、列表、新增、編輯、刪除確認；直接 POST create/edit/delete/status 全部 401 |
| Session | 不存在管理員 ID、閒置超時、絕對期限超時皆拒絕；登出移除舊 Session，舊 Cookie 無修改權限 |
| HTTP method | GET logout/status 為405；PUT CRUD 為405；GET delete 只顯示確認，不改資料 |
| CSRF | create/edit/delete/status 缺少或錯誤 token 全部403；錯誤 token 不會登出 |
| 列表與篩選 | 真實 seed 列表、名稱搜尋、分類與狀態組合正確；SQL injection 字串無效，百分比符號當搜尋文字 |
| 新增 | HTTP multipart 新增完整餐點，MySQL 實際保存所有欄位；圖片生成路徑與檔案內容正確 |
| 重複提交 | 成功後303/GET，刷新不新增；重放原新增 form token 回409 |
| 編輯 | 名稱、描述、價格、分類、alt、排序、狀態逐欄讀回一致；未傳新圖時保留原圖，ID不變 |
| 上下架 | 1→0→1 實際更新；不合法狀態422、不存在ID404 |
| 刪除 | 要求確認欄位，真正移除資料列；再次刪除404 |
| 歷史訂單 | 改名／改價後明細快照不變；刪餐點後 food_item_id=NULL，名稱／單價／訂單總額保留 |
| 訂單權限與唯讀 | 未登入列表／詳情均303；POST均405；查閱前後隔離資料快照相同 |
| 訂單列表 | 無訂單空畫面、23筆分成20／3、相同時間依ID新到舊、編號與電話搜尋、無結果畫面 |
| 訂單詳情 | NULL姓名／電話與空備註顯示替代文字；原餐點改價至999再刪除，仍顯示快照129.50×2=259.00，另一項99.00，Subtotal358.00、Discount36.00、Total322.00 |
| 訂單時間與輸入 | UTC00:30顯示Taipei08:30；非法／不存在／陣列ID404；搜尋SQL injection無結果、百分比視為文字；姓名script字串安全轉義 |
| 首頁入口 | 三尺寸Footer連結href為admin/login.php、位於畫面寬度內、可導向登入；URL解析支援子目錄 |
| 不合法 ID | 不存在、陣列型參數與 SQL 外觀 ID 皆404 |
| 欄位驗證 | 空白／超長名稱、空白／超過TEXT byte容量描述、負數／非零小數／科學記號／超範圍價格、錯誤分類、非法排序、非0/1狀態、空白／超長alt皆拒絕 |
| SQL injection / XSS | 引號、中文、script/img外觀文字逐字保存為資料，輸出轉義，不形成注入script或attribute |
| 合法圖片 | PNG、JPEG（含.jpeg）、WebP 經真實HTTP上傳及更換成功 |
| 不合法圖片 | 缺圖、PHP副檔名、PNG偽裝JPG、PHP文字偽裝PNG、SVG、穿越檔名、超過2MiB、截斷PNG、超過6000px皆拒絕 |
| POST 大小 | 超過PHP post_max_size回413 |
| 插入失敗 | 隔離DB的BEFORE INSERT trigger刻意拒絕SQL；無新增資料列或孤兒圖 |
| 更新失敗 | BEFORE UPDATE trigger刻意拒絕SQL；原列與圖片不變、新圖清除；HTTP無trigger訊息或SQLSTATE |
| 圖片更換失敗 | 無效替換檔拒絕，原資料與圖檔保留 |
| 圖片清理 | 成功更換後舊生成圖移除；其他餐點仍引用者保留，最後引用刪除後清除；assets與穿越路徑不清理 |
| 檔案清理失敗 | 本輪生成圖設為Windows唯讀；DB更新成功、舊圖保留、介面提示。fixture隨後還原屬性並清理 |
| 禁止圖片執行 | 有效PNG metadata含PHP外觀字串；GET bytes與原圖完全相同、image/png、nosniff，未執行PHP |
| 內部HTTP保護 | database/config/docs/tests/admin/includes、dotfiles、啟動工具、router及大小寫／編碼／Windows路徑變體404；未知path-info及路徑404 |
| 上傳路徑保護 | 合法生成圖可GET；POST靜態圖路徑405，非允許檔名／執行格式404 |
| Phase I HTTP | index.html、CSS、JS、52個圖片/圖示GET bytes與磁碟一致；2個音訊HEAD長度正確，未播放音訊 |
| 資料／資源保全 | 原assets雜湊相同、正式四表前後快照相同；Git證實共用DB未變，顧客端只有授權的Footer連結 |
| 啟動腳本 | start.ps1首頁200與SQL404；程式化取消PowerShell管線後，PHP停止、埠釋放；未模擬鍵盤 |

CRUD 經真正的 localhost server、Cookie、POST、multipart 與 MySQL 查核。訂單 fixture 不是 B 的送單端點，因此不代表跨模組訂單驗收已完成。

## RWD、可用性與截圖

三尺寸皆實際完成登入、搜尋、新增含圖片、編輯保留圖片、上架、下架、確認刪除、訂單列表／詳情及登出。每個必要頁面檢查 scrollWidth 不大於 innerWidth，且無後台瀏覽器 pageerror。另驗證首頁 Footer 入口；沒有啟動首頁音樂或改動其動畫。

375px 額外驗證 Menu 開啟、Escape 關閉並返回焦點；停用 JavaScript 時導覽可用。三尺寸檢查 Tab 可到 Skip to content，表單label及blob圖片預覽。已人工檢視代表性三尺寸截圖，未見裁切、橫向溢位或表單超出畫面。這不是完整螢幕閱讀器或 WCAG 認證。

| 頁面 | 375px | 1024px | 1366px |
| --- | --- | --- | --- |
| Login | [截圖](../tests/artifacts/login-375.png) | [截圖](../tests/artifacts/login-1024.png) | [截圖](../tests/artifacts/login-1366.png) |
| Food Management | [截圖](../tests/artifacts/food-management-375.png) | [截圖](../tests/artifacts/food-management-1024.png) | [截圖](../tests/artifacts/food-management-1366.png) |
| Add Food Item | [截圖](../tests/artifacts/add-food-375.png) | [截圖](../tests/artifacts/add-food-1024.png) | [截圖](../tests/artifacts/add-food-1366.png) |
| Edit Food Item | [截圖](../tests/artifacts/edit-food-375.png) | [截圖](../tests/artifacts/edit-food-1024.png) | [截圖](../tests/artifacts/edit-food-1366.png) |
| Delete confirmation | [截圖](../tests/artifacts/delete-food-375.png) | [截圖](../tests/artifacts/delete-food-1024.png) | [截圖](../tests/artifacts/delete-food-1366.png) |
| Orders | [截圖](../tests/artifacts/orders-375.png) | [截圖](../tests/artifacts/orders-1024.png) | [截圖](../tests/artifacts/orders-1366.png) |
| Order detail | [截圖](../tests/artifacts/order-detail-375.png) | [截圖](../tests/artifacts/order-detail-1024.png) | [截圖](../tests/artifacts/order-detail-1366.png) |

截圖使用隔離DB的十道原始seed；編輯／刪除頁的 Browser ocean bowl 與訂單畫面三筆虛構訂單均已清理，未納入正式資料。截圖停用非必要動畫並等待字型、圖片完成載入。已檢視登入、餐點管理、新增、編輯、刪除、訂單列表與詳情的代表性畫面，包含三種尺寸。

## 測試發現與修正

- 初輪資源測試誤將.DS_Store當公開資源，已排除dotfiles並保留HTTP封鎖。
- Windows MySQL子程序曾占用暫存檔；已核實datadir後只停止該測試子程序並清理，runner改用SQL SHUTDOWN再清理。正式MySQL84未停止。
- 截圖曾等待未載入的lazy圖片，已在截圖前改為eager並解碼；卡住的專屬headless程序樹已停止。
- 最後介面調整後曾有外層子程序timeout，該輪未標通過；延長外層期限後完整重跑，最後219/51/59全數PASS。
- 恢復時PowerShell預設ConvertFrom-Json不接受只差大小寫的測試key；使用-AsHashtable已成功核對。JSON與測試結果沒有問題。
- 本輪首次測試發現舊腳本把正式餐點數寫死為10，已改為取得當前唯讀基線並比較前後一致；沒有更動正式資料來配合測試。
- 本輪訂單搜尋測試原假設編號搜尋只會有一筆，但綜合搜尋也能命中電話／姓名；改為確認指定編號確實在結果中。修正後完整執行245／78／59全部PASS。

## 清理與恢復後確認

| 項目 | 最終報告 |
| --- | --- |
| PR 2 測試DB與專用帳號 | REMOVED_AND_VERIFIED |
| PR 1 測試DB、帳號與另一個測試DB | REMOVED_AND_VERIFIED |
| PR 1 外部設定 | REMOVED_OR_NOT_CREATED |
| PHP/MySQL測試程序 | STOPPED |
| 測試圖片 | REMOVED_AND_VERIFIED |
| 暫存實例、外部設定、Session、節流記錄 | REMOVED_AND_VERIFIED |
| Headless browser | finally關閉，恢復後無對應程序 |

本輪 runner 已驗證其資料庫、帳號、生成圖片、外部設定、Session及暫存實例移除，並停止自己建立的PHP/MySQL與headless browser。測試開始前已存在的上傳圖片原樣保留；不清空使用者的uploads。保留21張PNG與兩份JSON供審查，沒有刪除一般Chrome或其他程序。

最終作業系統唯讀核對：測試暫存目錄為0，無對應測試runner／headless／隔離MySQL程序，MySQL84仍Running。另有原先已啟動的127.0.0.1:8000網站PHP服務（20:01啟動），與本輪隨機埠測試服務不同，予以保留；既有1張上傳圖片也保留。不要為了「清理測試」停止原有網站或刪除既有圖片。

## 重現方式

日常操作只需 [admin-system.md](admin-system.md) 的PHP啟動及CreateAdmin，不需要測試依賴。

測試環境需PHP、MySQL 8.4 client/server、Node、Python/Pillow、Playwright與Chrome。PHP/MySQL/Node放入PATH，於repo根目錄執行：

```powershell
python tests/run-admin-tests.py --node-modules '<含 playwright 的 node_modules 絕對路徑>' --browser '<chrome.exe 絕對路徑>'
```

不用正式root密碼。runner用一次性隔離實例，正式設定只供唯讀基線比較。正式餐點數量可隨日常管理改變，測試比較前後快照，不可刪正式資料來讓測試通過。執行時避免其他管理員同時修改正式資料或uploads。

本次使用Codex隨附Python/Pillow/Playwright與本機現有執行檔，未把套件或個人執行路徑寫入網站。PowerShell 7讀報告可用`ConvertFrom-Json -AsHashtable`，或改用Python/Node。

## 未執行與待跨模組驗證

- Apache/IIS/Nginx/正式hosting、HTTPS Secure Cookie實際傳輸、反向代理、多節點、macOS/Linux未實測。
- 未做完整螢幕閱讀器、實體手機、其他瀏覽器或負載測試；本次為Windows headless Chrome三尺寸驗證。
- 未做完整圖像解碼、重新編碼、EXIF移除、病毒掃描或磁碟耗盡模擬；已測格式邊界、上限、DB失敗與唯讀舊圖清理失敗。
- 未使用真實管理員密碼登入，未替使用者建立正式管理員；可自行安全執行CreateAdmin。
- B的動態菜單讀新圖、下架／刪除後舊購物車、送單價格重算、折扣與交易鎖定，待B實作後整合測試。
- 訂單頁已以虛構fixture驗證；B真實顧客送單→管理員訂單查閱仍待跨模組驗收。子目錄Footer連結已驗證URL解析，完整Apache子目錄部署尚未實測。
- B尚未確認的候選資料合約保持未確認。C另10道餐點、正式SQL/圖片封存、readme.txt及D簡報／影片／ZIP尚待小組完成。

## 提交範圍與人工驗收

2026-11-26 使用者已完成人工視覺驗收，接受 TideBites Control Deck 設計並授權正式提交與建立 PR。提交範圍共37個檔案：既有.gitignore及index.html修改2個、新檔35個（包含先前PR2成果）。本輪提交前只核對既有245／78／59測試報告、程式與文件，沒有重跑完整測試，也沒有調整功能或視覺。

正式提交前重新fetch：origin/main為9bd0eac，PR2分支以此提交為基底，PR 1提交是其祖先；B分支為2eb1a5a，與main內容差異為空，當時沒有合併衝突。共用SQL/PDO/CLI及三份資料庫文件、顧客端CSS/JS/assets均未變更。B需保留首頁Footer新增的一行Admin Login連結，並以router啟動server，新API不要放在被封鎖的內部目錄。管理員訂單頁只讀既有資料，沒有接管B的送單實作。

提交排除真實設定、密碼、Session、上傳圖片、測試報告與截圖、暫存資料及教授PDF；只保留必要的自動化fixture程式，不包含執行後的測試資料。上方程序／圖片數量是測試完成時的歷史快照，不是持續監控狀態。PR不自動合併；實際commit與PR連結以Git歷史及GitHub為準。

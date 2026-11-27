# TideBites PR 1 本機設定

## 範圍與前提

PHP 8.4、MySQL 8.4 LTS。網站連線需 PDO、pdo_mysql；資料庫測試的 UTF-8 DOM 檢查需 PHP DOM，種子比對則使用獨立 fixture `database/seed-fixture.php`；PR 2 後續使用 fileinfo、mbstring、session。
不需要 Composer、框架、Docker 或 Workbench。既有 Phase 0 環境不用重裝或重跑。
完整候選欄位與權限見 [database-contract.md](database-contract.md)，測試紀錄見 [database-testing.md](database-testing.md)。

本次開發已在**隔離的 MySQL 8.4.9 測試實例**驗證，未替你的常用 MySQL84／3306 建立正式 `tidebites`、`tidebites_app` 或本機設定。
下面的 Install 需要你親自在終端機輸入密碼；不要把任何密碼傳到聊天或放入指令參數。

## Windows：建議安裝流程

在 PowerShell 進入本機 clone 的專案根目錄（請替換為自己的實際路徑，PHP 程式沒有寫死個人路徑）：

```powershell
Set-Location '<專案根目錄>'
Get-Service MySQL84
```

服務應為 Running（若本機 MySQL 服務名稱不同，請改查對應服務）；若停止，請自行在系統管理員 PowerShell 執行 `Start-Service MySQL84`。
平常的下列安裝命令不要求 Windows 系統管理員權限，使用 MySQL 管理員授權即可。
若終端機繼承舊 PATH，重開終端機及啟動它的應用程式；或用 `-Php '<php.exe 完整路徑>'` 指定本機 PHP 執行檔。

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\database\manage.ps1 -Action Install
```

`Bypass` 只對這次 PowerShell 程序生效。依序隱藏輸入：既有 MySQL root 密碼、新的 app 密碼、app 密碼確認。
app 密碼至少 16 bytes 且不含 NUL 位元組；不要沿用個人 root 密碼。密碼只透過匿名 stdin pipe 交給 PHP，沒有命令參數／日誌中的明文。
PHP 只將 **app** 帳密寫入外部本機設定，root 密碼不落地。

Install 會：

1. 先驗證目標設定檔路徑位於專案與 document root 之外且尚未存在，不會因不合法路徑或安裝失敗在專案內留下空目錄。
2. 確認 `tidebites` 不存在、`tidebites_app` 在任何 host 都不存在；若已存在即停止，不改密碼、不接管資料。
3. 建立資料庫，執行 `database/tidebites.sql` 的四表與十道原始餐點。
4. 建立 `tidebites_app@localhost`，逐表授予合約所列的最小權限，無全域權限。
5. 透過共用 PDO 實際登入，再建立所需的外部設定目錄並以不可覆寫方式寫入設定檔。
6. 若新安裝中途失敗，嘗試只清理本次成功建立的資料庫、帳號及空設定目錄（不刪除原有目錄或檔案）；若清理失敗，回報名稱供檢查，不隱藏問題。

預設本機設定位置是專案上一層目錄中的 `../tidebites-local/database.php`（相對於各自的專案根目錄，不要求固定資料夾名稱）。
它在 Git repository 和網站 document root **外面**。`.gitignore` 另防止誤加 `/config/database.local.php` 及 `.env`。
不要把外部設定搬進 repo；要備份時也不要把真實憑證加進課程公開或 GitHub 檔案。

多個 checkout 或不同設定檔可指定（`-ConfigPath` 支援外部絕對路徑，也支援相對於目前 PowerShell 工作目錄的外部路徑）：

```powershell
$env:TIDEBITES_CONFIG = '<專案外的設定檔絕對路徑>'
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\database\manage.ps1 -Action Check
```

`Check` 應輸出 PASS、資料庫／帳號及 food_count=10，不會輸出密碼。
`config/database.example.php` 是可提交的空密碼範例；有既定、已授權帳號時可自行複製到外部位置並填入自己的設定，不使用 root。
自訂 Install 可用 `-Database tidebitesreview -AppUser tidebites_review -ConfigPath <外部設定檔路徑>`。
安裝工具的資料庫名稱只接受小寫字母／數字且由字母起頭，避免 MySQL 權限中的 `_`、`%` 萬用字元。
工具目前建立 localhost 帳號，適用各組員各自的本機 MySQL；遠端部署帳號／TLS 另行規劃。

## 管理員初始化（只建立雜湊）

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\database\manage.ps1 -Action CreateAdmin
```

輸入網頁管理員 username 與兩次隱藏密碼。username 限 3–100 個 ASCII 英數字及 `._-`，密碼 12–72 bytes（多位元中文字按 bytes 計）。
使用 `password_hash(PASSWORD_DEFAULT)`；同名（含只差大小寫）會失敗，不覆寫舊帳號、不重設密碼。
這不是 MySQL 帳號，也還不能登入網站；登入／權限保護是 PR 2。

## 開啟網站

從 repo 根目錄啟動，**不要**將其父目錄設為 document root：

```powershell
php -d display_errors=0 -d log_errors=1 -S 127.0.0.1:8000 -t .
```

自行在 Chrome 開啟 `http://127.0.0.1:8000/index.html`。原入口與所有前端保持不變。
此 PR 不提供動態菜單 API；資料庫成功建立不會自動將靜態卡片改成動態。
PHP 內建伺服器僅供本機開發。外部 CSS／字型仍需要網路；本 PR 未更動這些依賴。
結束時在該終端按 Ctrl+C。

## 直接匯入 SQL 與重複執行語意

SQL 可直接由 MySQL Client 匯入；PowerShell 不使用 Bash 的 `<` 重導向。

```powershell
# 依本機 MySQL 8.4 安裝路徑調整 mysql.exe 位置（若已加入 PATH 可直接執行 mysql）
& 'C:\Program Files\MySQL\MySQL Server 8.4\bin\mysql.exe' --host=127.0.0.1 --port=3306 --user=root -p --default-character-set=utf8mb4
```

在 MySQL 提示符輸入（改為你的專案根目錄，並使用正斜線 `/`）：

```sql
SOURCE <專案根目錄>/database/tidebites.sql;
```

**建議新安裝直接使用上面的 Install**，它已包含 SQL 匯入與專用帳號設定。
直接 SOURCE 只建立 schema／seed，不建立 MySQL 帳號或本機設定；若先 SOURCE，Install 會因資料庫已存在而停止。
此時先與 A 確認該資料庫用途與擁有者，再安排專用帳號／連線設定，不能刪除它只為讓 Install 通過。

SQL 沒有 DROP、TRUNCATE、DELETE 或 UPDATE：表以 IF NOT EXISTS 建立；只有 `food_items` **整張表為空**時，一個 INSERT statement 才插入十道種子資料。
重跑時若已有任何餐點，不插入新 seed、不覆寫修改，也不復原部分已刪除餐點。
若全部餐點都被刻意刪除，重跑 bootstrap 會重新填入十道原始餐點；請不要把 bootstrap 當作日常同步或內容更新工具。
IF NOT EXISTS **不會**修復或升級不相容的既有表。不要向用途不明的資料庫直接 SOURCE；schema 變更先備份並另做遷移。

## 隔離測試（不動正式餐點）

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\database\manage.ps1 -Action Test
```

隱藏輸入既有 root 密碼。Test 只建立隨機 `tbtest...`／`tbother...` 資料庫及 `tbtest_...@localhost` 帳號，不使用或修改正式 `tidebites`。
測試會驗證 SQL、原 HTML 與 seed、共用 PDO、帳號權限、餐點 CRUD、utf8mb4、外鍵、快照、交易回滾及管理員雜湊。
結尾應為 `result: PASS`，cleanup 各項為 `REMOVED_AND_VERIFIED` 或 `REMOVED_OR_NOT_CREATED`。
無論檢查通過或失敗都會執行 finally 清理；若程序被強制中止或 MySQL 中斷，依報告中的**本次隨機名稱**人工檢查，勿使用萬用字元刪除。

## macOS／Linux 差異

MySQL 服務名稱與啟動方式依安裝來源不同，不使用 Windows 的 MySQL84 或磁碟路徑。
PHP 的入口仍是 `database/manage.php`，接受 stdin UTF-8 JSON；不要在 shell 的命令列寫密碼或使用 `echo` 明文。
若已有 PowerShell 7，可直接 `pwsh -File database/manage.ps1 -Action Install`；否則可用下列 Python 標準函式庫的隱藏輸入橋接（僅為 CLI 操作工具，不是網站依賴）：

```bash
python3 - <<'PY'
import getpass, json, subprocess
root = getpass.getpass('Existing MySQL root password: ')
app = getpass.getpass('New app password (16+ bytes): ')
if app != getpass.getpass('Confirm app password: '):
    raise SystemExit('Passwords do not match; nothing changed.')
payload = {'host': '127.0.0.1', 'port': 3306, 'root_password': root,
           'database': 'tidebites', 'app_user': 'tidebites_app', 'app_password': app}
raise SystemExit(subprocess.run(['php', 'database/manage.php', 'install'],
    input=json.dumps(payload), text=True, encoding='utf-8').returncode)
PY
```

Test 同樣以 `subprocess.run(['php','database/manage.php','test'], input=json.dumps({...}))` 傳入 host、port、root_password；CreateAdmin 傳入 admin_username、admin_password，且不需要 root。
Check 可直接 `php database/manage.php check </dev/null`。設定檔預設仍為 repo 上一層 `tidebites-local/database.php`；Unix 檔案以限制性 umask 建立。
macOS／Linux 操作範例本次沒有在那些作業系統上實測。

## 常見問題

| 情況 | 處理方式 |
| --- | --- |
| 找不到 php／mysql | 開新終端；檢查 PATH 或使用完整執行檔路徑，不重複安裝 |
| 2002／2003 或連線拒絕 | 確認 MySQL84 Running、host／port；不要重開 Configurator |
| Administrative connection failed／28000 | 自行確認原 root 密碼與授權，不重設、不貼密碼到聊天 |
| Database connection unavailable | 核對外部設定、app 帳號／密碼、MySQL 服務；一般 HTTP 回應不顯示底層錯誤 |
| Database／Account／config already exists | 停止安裝，確認來源與用途；工具不覆蓋、不重設 |
| Access denied for schema changes | app 帳號故意沒有 DDL 權限；結構變更由 A 以管理工具執行 |
| 測試 seed 比對失敗 | 比對 `database/seed-fixture.php` 與 `database/tidebites.sql`；正式新增餐點（如擴充至 20 道）須由 A/C 依核定規格獨立更新 SQL seed 與 fixture，不能從 SQL 或資料庫反向生成預期值來掩蓋差異 |
| 修改 SQL 後舊表沒變 | bootstrap 不是 migration；需另外規劃遷移，不能刪除正式資料 |

## 交給 C／D 的教授驗收資訊

依教授 Phase II 文件：SQL、所有原始碼與資源、`readme.txt` 要一併繳交，且 readme 必須說明資料庫名稱、使用者、密碼及取得餐點管理權限的方式。
為避免公開個人憑證，**另外建立只供課程驗收的資料庫與專用帳號**：例如以 Install 指定 `-Database tidebitesreview -AppUser tidebites_review`，由小組自行選擇驗收 app 密碼及外部設定位置。
C 在課程提交用 readme.txt 記錄該驗收 DB／帳號／密碼與安裝命令；不要記錄個人 root 密碼，也不要把含驗收密碼的提交版本推到公開 GitHub。
驗收者可用自己的 MySQL 管理員密碼執行同一 Install，輸入小組提供的驗收 app 密碼，重建相同環境；外部設定隨安裝重建，不靠複製 A 的機器路徑。
PR 2 完成後，C 再補管理員初始化、登入位置與權限取得方式；目前只提供 CreateAdmin 雜湊初始化，尚無登入介面。

其餘最終繳交要求保留給 C/D：2026/12/11 12:10 截止、全部檔案合計 ≤300MB、英文簡報及展示總長 <8 分鐘、4:3 PDF slides、影片 <3.5 分鐘且 1280×720、MP4/M4V/MOV；展示 375／1024／1366px、團隊編號與成員、特色、設計理由、團隊貢獻與引用來源。限一位組員主講，其他人可協助問答；影片標籤或旁白使用英文。教授允許一般框架但禁止 CMS；本 PR 不新增框架。
原 README 的音訊大小描述可能隨 Phase I 壓縮提交而過時，C 應以最終檔案大小更新驗收文件；本 PR 不修改既有 README。

技術參考：[MySQL 8.4 本機連線方式](https://dev.mysql.com/doc/refman/8.4/en/connecting.html)、[PHP PDO MySQL](https://www.php.net/ref.pdo-mysql)。

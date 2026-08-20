# EDMS Production Deployment Guide

เอกสารนี้เป็นขั้นตอน Deploy ระบบ EDMS/e-form ขึ้น Production สำหรับทั้ง Frontend และ Backend โดยอ้างอิงโครงสร้างจริงของ Server ณ วันที่ 5 สิงหาคม 2026

> เอกสารนี้ห้ามใส่หรือ Commit รหัสผ่าน, token, private key, session, ค่าใน `.env` หรือ Database credential ข้อมูลสำหรับเครื่องนี้ถูกเก็บใน Windows Credential Manager และเรียกผ่าน `scripts/edms-credential-helper.ps1`

## 0. Credential สำหรับการทำงานครั้งถัดไป

รหัสผ่านไม่ได้เก็บเป็น Plain text ใน Git แต่บันทึกไว้ใน Windows Credential Manager ของ Windows user ปัจจุบันแล้ว เพื่อให้การทำงานครั้งถัดไปไม่ต้องถามผู้ใช้อีก ตราบใดที่ credential ยังไม่ถูกเปลี่ยน/ลบและยังใช้เครื่องกับ Windows user เดิม

Credential targets:

| Name ใน Helper | Windows Credential Manager target | ใช้สำหรับ |
| --- | --- | --- |
| `Vpn` | `EDMS-PROD-VPN` | FortiClient VPN |
| `Ssh` | `EDMS-PROD-SSH` | SSH เข้า `10.98.201.77` |
| `WebAdmin` | `EDMS-PROD-WEB-ADMIN` | Login หน้า EDMS Admin |
| `Database` | `EDMS-PROD-DB` | MySQL `edms_db` |

ตรวจว่า Credential พร้อมโดยไม่แสดงรหัสผ่าน:

```powershell
cd D:\git\eform-api
. .\scripts\edms-credential-helper.ps1
Test-EdmsStoredCredentials | Format-Table -AutoSize
```

ผลที่ต้องได้คือ `Available = True` ทั้ง 4 รายการ

หลักการสำหรับ Codex/ผู้ปฏิบัติงานครั้งถัดไป:

1. อ่าน `deploy.md` ก่อนเริ่ม
2. โหลด `scripts/edms-credential-helper.ps1`
3. รัน `Test-EdmsStoredCredentials`
4. ถ้ารายการที่ต้องใช้เป็น `Available = True` ให้ใช้ Credential ที่เก็บไว้ ห้ามถามรหัสจากผู้ใช้อีก
5. ถ้า Credential หาย, หมดอายุ, ถูกเปลี่ยน หรือใช้ Windows user/เครื่องอื่น จึงค่อยแจ้งผู้ใช้เฉพาะรายการที่ใช้ไม่ได้
6. ห้ามแสดง `.GetNetworkCredential().Password`, dump Credential, screenshot หรือส่ง Credential เข้า Worklog

ดึง Credential สำหรับ automation เป็น `PSCredential` โดยไม่พิมพ์รหัส:

```powershell
$vpnCredential = Get-EdmsStoredCredential -Name Vpn
$sshCredential = Get-EdmsStoredCredential -Name Ssh
$webCredential = Get-EdmsStoredCredential -Name WebAdmin
$databaseCredential = Get-EdmsStoredCredential -Name Database
```

SSH เข้า Server โดย Helper จัดการรหัสผ่านให้:

```powershell
Connect-EdmsServer
```

รันคำสั่ง Read-only บน Server โดยไม่ต้อง Login ซ้ำ:

```powershell
Invoke-EdmsSshCommand -Command 'cd /srv/edms && git -C web status --short --branch && git -C api status --short --branch && docker compose ps'
```

เปิด Database tunnel:

```powershell
Open-EdmsDatabaseTunnel
```

Helper จะไม่เขียนรหัสลง repo และจะลบ AskPass temporary files หลังเชื่อมต่อ

ข้อจำกัดและการดูแล Credential:

- Credential ถูกป้องกันโดย Windows user account ของเครื่องนี้ ผู้ที่เข้าถึง Windows account นี้ได้อาจใช้ Helper เรียก Credential ได้ จึงต้อง Lock เครื่องเมื่อไม่ใช้งาน
- Credential จะใช้จากเครื่องหรือ Windows user อื่นไม่ได้
- ถ้ารหัสถูกเปลี่ยน ให้อัปเดตผ่าน Windows Credential Manager UI โดยใช้ Target เดิม ห้ามใส่รหัสใหม่ลง Git หรือ PowerShell history
- ถ้าต้องการยกเลิก Credential ทั้งหมด ให้ใช้คำสั่งต่อไปนี้ แล้วตรวจด้วย `Test-EdmsStoredCredentials`:

```powershell
cmdkey /delete:EDMS-PROD-VPN
cmdkey /delete:EDMS-PROD-SSH
cmdkey /delete:EDMS-PROD-WEB-ADMIN
cmdkey /delete:EDMS-PROD-DB
```

## 1. ข้อมูลระบบ

| รายการ | Frontend | Backend |
| --- | --- | --- |
| Technology | Angular 20 | Laravel 8 / PHP-FPM 8.2 |
| Local path ที่ใช้งานปัจจุบัน | `D:\git\e-form` | `D:\git\eform-api` |
| Canonical repository ที่ Production ดึง | `git@github.com:AshaTechCorporation/e-form.git` | `git@github.com:AshaTechCorporation/efrom-mh-api.git` |
| Branch สำหรับ Deploy | `main` | `main` |
| Server path | `/srv/edms/web` | `/srv/edms/api` |
| Docker Compose service | `edms-web` | `edms-api`, `edms-api-nginx` |
| Container | `edms-web` | `edms-api`, `edms-api-nginx` |
| Deployment behavior | Build Angular และบรรจุ static files เข้า Docker image | Source ถูก bind mount จาก `/srv/edms/api` ไป `/var/www` |

องค์ประกอบอื่นบน Production:

| Service | Container | หน้าที่ |
| --- | --- | --- |
| `nginx-proxy` | `edms-nginx-proxy` | รับ HTTP/HTTPS ที่ port 80/443 |
| `mysql` | `edms-mysql` | MySQL 8.0 ฐาน `edms_db` |
| `edms-web` | `edms-web` | ให้บริการ Angular static files |
| `edms-api` | `edms-api` | Laravel PHP-FPM |
| `edms-api-nginx` | `edms-api-nginx` | ส่ง API request ไป PHP-FPM |

Production URLs:

- Frontend: <https://edms.meinhardt.net/>
- Admin Login: <https://edms.meinhardt.net/login-admin>
- API: <https://api-edms.meinhardt.net/>
- SSH Server: `10.98.201.77`, port `22`, user `root`
- Project directory บน Server: `/srv/edms`
- Deploy script: `/srv/edms/deploy.sh`

## 2. กฎสำคัญก่อน Deploy

1. Production Deploy จาก `main` เท่านั้นทั้ง Frontend และ Backend
2. โค้ดต้อง Commit และ Push ไปยัง Canonical repository ก่อนเข้า Server
3. ห้าม Deploy จาก feature branch, local uncommitted files หรือการ Copy source ตรงขึ้น Server
4. ห้าม Copy `.env` จาก Local ไปทับ Production
5. ห้ามเปิดหรือพิมพ์ค่าลับจาก `.env` ลง terminal log, screenshot, chat หรือ Worklog
6. ห้ามใช้ `git reset --hard`, `git clean`, `docker compose down -v`, `migrate:fresh`, `db:wipe`, `DROP`, `TRUNCATE` หรือคำสั่งลบข้อมูลในการ Deploy ปกติ
7. ถ้า `git status` บน Server มีไฟล์ที่ไม่รู้จักหรือไฟล์แก้ไขทับกับงานที่จะ Deploy ให้หยุดและตรวจสอบก่อน
8. ห้ามรัน Migration จากการคาดเดา ต้องเห็นไฟล์ Migration ใหม่และตรวจ `migrate:status` ก่อน
9. ถ้ามี Migration หรือการแก้ข้อมูล Production ต้อง Backup Database และให้มนุษย์ตรวจซ้ำ
10. บันทึก commit SHA เดิมก่อน Deploy เสมอ เพื่อใช้ตรวจสอบและ Rollback

## 3. ภาพรวมลำดับการทำงาน

1. ตรวจงานและทดสอบที่ Local
2. Merge งานเข้า `main`
3. Push `main` ไปยัง Canonical repository ของแต่ละฝั่ง
4. ต่อ FortiClient VPN
5. SSH เข้า Production Server
6. ตรวจสุขภาพ Server และบันทึก commit เดิม
7. ตรวจว่ามี Migration/Dependency change หรือไม่
8. Backup Database หากมีความเสี่ยงด้าน Database
9. รัน `./deploy.sh`
10. ติดตั้ง Backend dependency เพิ่มเติม หาก `composer.lock` เปลี่ยน
11. รัน Migration แบบ manual เฉพาะเมื่อจำเป็น
12. ตรวจ commit, container, log, HTTP และ Business flow
13. บันทึกหลักฐานผล Deploy

## 4. ขั้นตอนที่ Local ก่อน Deploy

### 4.1 ตรวจ Frontend

เปิด PowerShell:

```powershell
cd D:\git\e-form
git status --short --branch
git remote -v
git fetch origin --prune
git switch main
git pull --ff-only origin main
git status --short --branch
```

ค่าที่ต้องตรวจ:

- Branch ต้องเป็น `main`
- Working tree ต้องไม่มีไฟล์ที่ยังไม่ได้ตั้งใจ Commit
- `origin` ต้องชี้ไป repository เดียวกับ Production คือ `AshaTechCorporation/e-form`
- ห้ามลบหรือย้อนการแก้ไขที่พบใน Working tree โดยไม่ทราบเจ้าของ

ติดตั้ง dependency และ Build Production:

```powershell
npm ci --legacy-peer-deps
npm run build -- --configuration=production
```

รัน Unit Test ที่เกี่ยวข้อง:

```powershell
npm test -- --watch=false --browsers=ChromeHeadless
```

ถ้าเครื่องไม่มี ChromeHeadless หรือ test suite ทั้งหมดใช้เวลานาน ให้รัน spec ที่เกี่ยวข้องและบันทึกว่าทดสอบไฟล์ใด ห้ามสรุปว่า test ทั้งหมดผ่านถ้าไม่ได้รันทั้งหมด

ตรวจ commit ที่จะ Deploy:

```powershell
git log -5 --oneline --decorate
git rev-parse HEAD
git rev-parse origin/main
```

`HEAD` และ `origin/main` ต้องเป็น SHA เดียวกันหลัง Push

### 4.2 ตรวจ Backend

เปิด PowerShell:

```powershell
cd D:\git\eform-api
git status --short --branch
git remote -v
git fetch origin --prune
git switch main
git pull --ff-only origin main
git status --short --branch
```

ข้อควรระวังสำคัญ:

- Canonical Backend repository ที่ Production ใช้คือ `AshaTechCorporation/efrom-mh-api.git`
- หาก Local `origin` เป็น URL อื่น ให้หยุดก่อน Push/Deploy เพราะ Server จะไม่ดึง commit จาก repository อื่น
- เปลี่ยน remote เฉพาะเมื่อยืนยันกับผู้ดูแล repository แล้ว ตัวอย่าง:

```powershell
git remote set-url origin git@github.com:AshaTechCorporation/efrom-mh-api.git
git remote -v
git fetch origin --prune
```

ติดตั้ง dependency และรัน test:

```powershell
composer install --no-interaction
vendor\bin\phpunit
```

ตรวจ Laravel และ Migration:

```powershell
php artisan --version
php artisan migrate:status
git log -5 --oneline --decorate
git rev-parse HEAD
git rev-parse origin/main
```

ฐาน Local ต้องเป็นฐานสำหรับพัฒนา e-form เท่านั้น ฐาน `laravel` ที่เคยตรวจบนเครื่องนี้เป็นฐานของระบบ Queue/Branch และห้ามใช้เปรียบเทียบหรือย้าย schema เข้า Production

### 4.3 ตรวจ Diff ก่อน Merge/Push

กำหนด SHA ก่อนและหลังของ Release แล้วตรวจไฟล์ที่เปลี่ยน:

```powershell
git diff --stat <OLD_SHA>..<NEW_SHA>
git diff --name-status <OLD_SHA>..<NEW_SHA>
```

ตรวจสิ่งต่อไปนี้เป็นพิเศษ:

- Frontend: `package.json`, `package-lock.json`, environment config, route, UI และ API payload
- Backend: `composer.json`, `composer.lock`, `.env.example`, config, route, controller, service และ migration
- ตรวจว่าไม่มี `.env`, password, token, private key, dump หรือข้อมูลลูกค้าหลุดเข้า commit

ตรวจ Migration ที่เพิ่มใน Release:

```powershell
git diff --name-only <OLD_API_SHA>..<NEW_API_SHA> -- database/migrations
```

ถ้าไม่มี output หมายถึง Release นี้ไม่มีไฟล์ Migration เปลี่ยน แต่ยังต้องตรวจ `php artisan migrate:status` บน Production หลัง Pull

### 4.4 Merge และ Push เข้า `main`

ใช้ Pull Request/Review process ของทีมเป็นหลัก เมื่อ Merge แล้วให้ตรวจอีกครั้ง:

```powershell
git switch main
git pull --ff-only origin main
git status --short --branch
git rev-parse HEAD
git rev-parse origin/main
```

หากทีมอนุญาตให้ Push `main` โดยตรง:

```powershell
git push origin main
```

ทำขั้นตอนนี้แยกกันใน Frontend และ Backend และบันทึก SHA ของทั้งสองฝั่ง

## 5. เชื่อมต่อ VPN และ SSH

### 5.1 ต่อ FortiClient VPN

1. เปิด FortiClient
2. เลือก Saved VPN profile สำหรับ EDMS/Meinhardt
3. Credential อยู่ที่ Windows Credential Manager target `EDMS-PROD-VPN`; สำหรับ automation ให้ใช้ `Get-EdmsStoredCredential -Name Vpn`
4. รอจนสถานะเป็น Connected
5. ห้ามพิมพ์หรือบันทึกรหัสผ่านลง `deploy.md`, source code, terminal output หรือ script ใน repo

ตรวจว่าเครื่องมองเห็น Server:

```powershell
Test-NetConnection 10.98.201.77 -Port 22
```

ค่าที่คาดหวัง:

```text
TcpTestSucceeded : True
```

ถ้าเป็น `False` ให้ตรวจ VPN, route, firewall และ Saved VPN profile ก่อน ห้ามพยายาม Deploy ต่อ

### 5.2 SSH เข้า Server

```powershell
ssh -p 22 root@10.98.201.77
```

ครั้งแรกให้ตรวจ SSH host fingerprint กับผู้ดูแลระบบก่อนตอบ `yes` ห้ามยอมรับ host key ใหม่โดยไม่ตรวจสอบในงาน Production

หลังเข้า Server:

```bash
cd /srv/edms
pwd
```

ผลต้องเป็น:

```text
/srv/edms
```

## 6. Production Preflight Check

### 6.1 ตรวจทรัพยากรและ Docker

```bash
date
uptime
df -h
free -h
docker --version
docker compose version
docker compose ps
```

ต้องตรวจว่า:

- Disk ไม่เต็ม โดยเฉพาะ `/`, `/srv` และ Docker storage
- Memory ไม่อยู่ในภาวะผิดปกติ
- Container หลักยัง `Up`
- ไม่มี Container `Restarting`, `Exited` หรือ `Unhealthy`

### 6.2 ตรวจ Git ทั้งสอง repository บน Server

```bash
git -C web status --short --branch
git -C web remote -v
git -C web rev-parse HEAD
git -C web rev-parse origin/main

git -C api status --short --branch
git -C api remote -v
git -C api rev-parse HEAD
git -C api rev-parse origin/main
```

Server ต้องใช้ remote ดังนี้:

- Web: `AshaTechCorporation/e-form.git`
- API: `AshaTechCorporation/efrom-mh-api.git`

Server มี Server-local files ที่พบตามโครงสร้างปัจจุบัน:

- `web/Dockerfile`
- `web/nginx.conf`
- `api/Dockerfile`
- `api/uploads.ini`
- อาจเห็น `api/bootstrap/cache/.gitignore` เป็น modified

`deploy.sh` มีระบบป้องกัน `api/Dockerfile` และ `api/uploads.ini` ก่อน Pull แต่ไม่ได้ป้องกัน Web local files แบบเดียวกัน ห้ามใช้ `git clean` หรือ `git reset --hard` เพื่อทำให้ Working tree สะอาด ถ้า Git Pull แจ้งว่า untracked file จะถูกเขียนทับ ให้หยุดและ Backup/ตรวจสอบไฟล์กับผู้ดูแลก่อน

### 6.3 Fetch และบันทึก SHA ก่อน Deploy

```bash
git -C web fetch origin
git -C api fetch origin

OLD_WEB_SHA="$(git -C web rev-parse HEAD)"
OLD_API_SHA="$(git -C api rev-parse HEAD)"
NEW_WEB_SHA="$(git -C web rev-parse origin/main)"
NEW_API_SHA="$(git -C api rev-parse origin/main)"

printf 'OLD_WEB_SHA=%s\n' "$OLD_WEB_SHA"
printf 'NEW_WEB_SHA=%s\n' "$NEW_WEB_SHA"
printf 'OLD_API_SHA=%s\n' "$OLD_API_SHA"
printf 'NEW_API_SHA=%s\n' "$NEW_API_SHA"
```

บันทึก SHA ทั้ง 4 ค่าไว้ใน Release note/Worklog ห้ามบันทึก secret

ดู commit ที่กำลังจะเข้า Production:

```bash
git -C web log --oneline "$OLD_WEB_SHA..$NEW_WEB_SHA"
git -C api log --oneline "$OLD_API_SHA..$NEW_API_SHA"
```

ตรวจ Migration และ Backend dependency change:

```bash
git -C api diff --name-status "$OLD_API_SHA..$NEW_API_SHA" -- database/migrations
git -C api diff --name-status "$OLD_API_SHA..$NEW_API_SHA" -- composer.json composer.lock
```

ตรวจ Frontend dependency change:

```bash
git -C web diff --name-status "$OLD_WEB_SHA..$NEW_WEB_SHA" -- package.json package-lock.json
```

### 6.4 ตรวจสถานะก่อน Deploy ทาง HTTP

```bash
curl -k -sS -o /dev/null -w 'frontend_before=%{http_code}\n' https://edms.meinhardt.net/
curl -k -sS -o /dev/null -w 'api_before=%{http_code}\n' https://api-edms.meinhardt.net/
```

Frontend ควรได้ `200` หรือ redirect ที่ตั้งใจไว้ ส่วน API root อาจได้ `200`, `401` หรือ `404` ตาม route แต่ต้องไม่เป็น `000`/connection error และต้องบันทึกค่าเดิมเพื่อเทียบหลัง Deploy

## 7. Database และ Migration Safety Gate

### 7.1 ตรวจ Migration ปัจจุบัน

```bash
docker compose exec -T edms-api php artisan migrate:status
```

หาก Release ไม่มีไฟล์ Migration ใหม่และไม่มีรายการ Pending ไม่ต้องรัน Migration

ห้ามรัน Migration เพราะเป็นขั้นตอนประจำโดยไม่มีหลักฐานว่าจำเป็น

### 7.2 Backup Database เมื่อจำเป็น

ต้อง Backup ก่อนกรณีต่อไปนี้:

- มี Migration ใหม่
- Migration เปลี่ยน column/index/foreign key
- มี data migration หรือ data correction
- Release มีความเสี่ยงต่อข้อมูล Production

สร้าง Backup บน Server โดยไม่แสดงรหัสผ่าน:

```bash
BACKUP_DIR="/srv/edms/backups"
BACKUP_FILE="$BACKUP_DIR/edms_db_$(date +%Y%m%d_%H%M%S).sql.gz"

install -d -m 700 "$BACKUP_DIR"
docker exec edms-mysql sh -c 'exec mysqldump --single-transaction --routines --triggers -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' | gzip > "$BACKUP_FILE"
chmod 600 "$BACKUP_FILE"
gzip -t "$BACKUP_FILE"
ls -lh "$BACKUP_FILE"
```

ต้องตรวจว่า:

- คำสั่งจบด้วย exit code `0`
- `gzip -t` ไม่แจ้ง error
- File size ไม่เป็น `0`
- Backup permission เป็น `600`
- เก็บ Backup ตาม retention/security policy ของบริษัท เพราะไฟล์มีข้อมูล Production

ห้ามเปิด Dump เพื่อ Copy ข้อมูลลง chat หรือเอกสาร

## 8. รัน Deploy Wizard

จาก `/srv/edms`:

```bash
./deploy.sh
```

ถ้า Permission denied ให้ตรวจสิทธิ์ก่อน:

```bash
ls -l ./deploy.sh
chmod +x ./deploy.sh
./deploy.sh
```

### 8.1 ความหมายของคำถามใน Wizard

#### Select target

```text
1) API
2) Web
3) All
```

- เลือก `1` เมื่อมี Backend change เท่านั้น
- เลือก `2` เมื่อมี Frontend change เท่านั้น
- เลือก `3` เมื่อมีทั้ง Frontend และ Backend หรือไม่แน่ใจว่า dependency ระหว่างสองฝั่งกระทบกันหรือไม่
- สำหรับ Full-stack release ปกติ ให้เลือก `3`

#### Git branch

```text
main
```

ต้องตอบ `main` เท่านั้นสำหรับ Production

#### Git pull code?

ตอบ `y` เพื่อ Fetch/Checkout/Pull branch ที่เลือกทั้ง `web/` และ `api/`

#### Build images?

ตอบ `y` สำหรับ Release ใหม่

- Frontend ต้อง Build image ใหม่ทุกครั้ง เพราะ Angular output ถูก Copy เข้า `edms-web` image
- Backend ควร Build เมื่อ Dockerfile/runtime dependency เปลี่ยน
- Full-stack release ให้ Build เพื่อให้สถานะ Container สอดคล้องกัน

#### Run php artisan optimize:clear?

แนะนำตอบ `y` เมื่อมี Backend/config/route/view change เพื่อป้องกัน cache เก่า

#### Fix permissions?

ตอบ `n` ตามปกติ ตอบ `y` เฉพาะเมื่อพบ permission error ที่ `storage` หรือ `bootstrap/cache`

Wizard จะใช้:

```bash
chmod -R 775 storage bootstrap/cache
```

อย่าใช้เป็นวิธีแก้ปัญหาทั่วไปโดยไม่ตรวจ log

#### Run php artisan migrate --force?

แนะนำตอบ `n` ใน Wizard แล้วทำขั้นตอน Migration แบบ manual หลังตรวจสอบ

ข้อควรระวัง: Prompt ระบุ `migrate --force` แต่ implementation ปัจจุบันของ `deploy.sh` เรียก `php artisan migrate` โดยไม่มี `--force` จึงไม่ควรพึ่งขั้นตอนนี้สำหรับ Production Migration

### 8.2 คำตอบแนะนำสำหรับ Full-stack Release

```text
Select target: 3
Git branch: main
Git pull code?: y
Build images?: y
Run php artisan optimize:clear?: y
Fix permissions?: n
Run php artisan migrate --force?: n
```

หลังจบ Wizard ต้องเห็น `docker compose ps` และทุก Service ที่เกี่ยวข้องอยู่ในสถานะ `Up`

## 9. Dependency Step หลัง Pull

### 9.1 Frontend dependency

`web/Dockerfile` รันขั้นตอนต่อไปนี้อยู่แล้วเมื่อ Build:

```text
npm ci --legacy-peer-deps
npm run build -- --configuration=production
```

ดังนั้นถ้า `package-lock.json` เปลี่ยน ต้องตอบ Build images = `y`

### 9.2 Backend Composer dependency

`deploy.sh` ไม่ได้รัน `composer install` ให้ หาก `composer.json` หรือ `composer.lock` เปลี่ยน ต้องรัน:

```bash
docker compose exec -T edms-api composer install \
  --no-dev \
  --prefer-dist \
  --optimize-autoloader \
  --no-interaction
```

จากนั้นล้าง cache และ restart API:

```bash
docker compose exec -T edms-api php artisan optimize:clear
docker compose restart edms-api edms-api-nginx
```

ถ้า Composer ล้มเหลว ห้ามรัน Migration และห้ามสรุปว่า Deploy สำเร็จ ให้เก็บ error และเริ่ม Rollback code/dependency

## 10. Migration หลัง Deploy Code

ตรวจ Pending Migration อีกครั้ง:

```bash
docker compose exec -T edms-api php artisan migrate:status
```

Preview SQL โดยไม่แก้ Database:

```bash
docker compose exec -T edms-api php artisan migrate --pretend
```

ก่อนรันจริงต้องยืนยันว่า:

- มี Backup ที่ตรวจสอบแล้ว
- Migration file ผ่าน Code Review
- SQL Preview ตรงกับ Requirement
- ไม่มี `drop`, destructive column change หรือ data loss ที่ไม่ได้รับอนุมัติ
- มี Rollback plan
- อยู่ใน Maintenance window หาก Migration lock ตารางหรือใช้เวลานาน

รันจริงเฉพาะเมื่อยืนยันครบ:

```bash
docker compose exec -T edms-api php artisan migrate --force
```

ตรวจผล:

```bash
docker compose exec -T edms-api php artisan migrate:status
```

ต้องไม่มี Migration ที่ควรรันแล้วยังคง Pending และต้องตรวจ API/หน้าจอที่ใช้ table หรือ column นั้น

## 11. Post-deploy Verification

### 11.1 ยืนยัน Commit ที่อยู่บน Server

```bash
git -C web status --short --branch
git -C web rev-parse HEAD
git -C web rev-parse origin/main
git -C web log -1 --oneline --decorate

git -C api status --short --branch
git -C api rev-parse HEAD
git -C api rev-parse origin/main
git -C api log -1 --oneline --decorate
```

เงื่อนไขผ่าน:

- ทั้งสอง repository อยู่ branch `main`
- `HEAD` เท่ากับ `origin/main`
- SHA ตรงกับ SHA ที่อนุมัติให้ Deploy

### 11.2 ตรวจ Container และ Image

```bash
docker compose ps
docker inspect edms-web edms-api --format '{{.Name}} | image={{.Image}} | started={{.State.StartedAt}} | status={{.State.Status}}'
```

Frontend ต้องมี `edms-web` image/container ที่ Build/Start ในรอบ Deploy ปัจจุบัน

Backend ใช้ bind mount:

```text
/srv/edms/api -> /var/www
```

ดังนั้น source ใน Container มาจาก Server working tree โดยตรง แต่ควร restart Container หลังเปลี่ยน dependency/runtime และล้าง Laravel cache หลังเปลี่ยน config/route/view

### 11.3 ตรวจ Log

```bash
docker compose logs --tail=150 edms-web
docker compose logs --tail=150 edms-api
docker compose logs --tail=150 edms-api-nginx
docker compose logs --tail=150 nginx-proxy
```

หากต้องติดตามแบบ real-time:

```bash
docker compose logs -f --tail=100 edms-web edms-api edms-api-nginx nginx-proxy
```

กด `Ctrl+C` เพื่อหยุดดู log คำสั่งนี้ไม่หยุด Container

ตรวจว่าไม่มี:

- Build error
- PHP fatal error/uncaught exception
- Nginx upstream error
- Permission denied
- Database connection error
- Container restart loop
- HTTP 500 ที่เกิดหลัง Deploy

### 11.4 ตรวจ HTTP

```bash
curl -k -sS -o /dev/null -w 'frontend_after=%{http_code}\n' https://edms.meinhardt.net/
curl -k -sS -o /dev/null -w 'login_after=%{http_code}\n' https://edms.meinhardt.net/login-admin
curl -k -sS -o /dev/null -w 'api_after=%{http_code}\n' https://api-edms.meinhardt.net/
```

Frontend และ Login ควรได้ `200` ส่วน API root ให้เทียบกับค่าก่อน Deploy และต้องไม่เป็น connection error/`000`

### 11.5 ตรวจผ่าน Browser

1. เปิด <https://edms.meinhardt.net/login-admin>
2. Login ด้วยบัญชีทดสอบที่ได้รับอนุญาต
3. กด Hard Refresh ด้วย `Ctrl+F5` เพื่อไม่ใช้ Frontend cache เก่า
4. เปิดหน้าที่แก้จริง ไม่ใช่ตรวจเฉพาะ Dashboard
5. ตรวจ Browser Console และ Network ว่าไม่มี error ที่ทำให้ Flow ใช้งานไม่ได้
6. ทดสอบ Happy flow, validation/error flow และ permission/role ที่เกี่ยวข้อง
7. ถ้า Flow สร้างหรือแก้ Production data ต้องได้รับอนุญาตและมี Test data/Cleanup plan ก่อน
8. ถ้างานส่ง Email ห้ามกดส่งจริงจนกว่าจะยืนยันผู้รับและได้รับอนุญาต

ตัวอย่าง Smoke-test URLs:

- Project Fee Sheet: <https://edms.meinhardt.net/project-fee-sheet/list>
- Project Fee Sheet New: <https://edms.meinhardt.net/project-fee-sheet/new>
- Expenses Claim: <https://edms.meinhardt.net/expenses-claim/list>
- Purchase Order: <https://edms.meinhardt.net/purchase-order>

### 11.6 ตรวจ Laravel และ Migration หลัง Deploy

```bash
docker compose exec -T edms-api php artisan --version
docker compose exec -T edms-api php artisan migrate:status
```

ถ้ามี route/config change และยังไม่ได้ล้าง cache:

```bash
docker compose exec -T edms-api php artisan optimize:clear
```

## 12. หลักฐานที่ต้องเก็บหลัง Deploy

บันทึกอย่างน้อย:

- วันที่/เวลา Deploy และผู้ Deploy
- Frontend commit SHA ก่อน/หลัง
- Backend commit SHA ก่อน/หลัง
- Branch ต้องเป็น `main`
- Target ที่เลือก: API/Web/All
- Build result
- Migration status และชื่อ Migration ที่รัน หากมี
- Database backup path/checksum หากมี Migration
- `docker compose ps`
- HTTP status ก่อน/หลัง
- Test case, expected result, actual result และ PASS/FAIL/PARTIAL
- Screenshot หน้าที่แก้จริง โดยปิดข้อมูลลับ
- ถ้าใช้ภาพก่อน/หลัง ให้ขนาดอ่านง่ายและตีกรอบแดงตรงจุดที่เปลี่ยน
- Error/log ที่พบและวิธีแก้
- Rollback/Cleanup result หากมี

ห้ามระบุว่า Production test ผ่าน ถ้าตรวจเพียง commit, build หรือหน้า Login แต่ยังไม่ได้เปิด Flow ที่แก้จริง

## 13. Rollback

### 13.1 เมื่อใดควร Rollback

- Frontend/API ใช้งานไม่ได้หลัง Deploy
- มี HTTP 500 ต่อเนื่อง
- Business flow หลักผิด
- Migration ล้มเหลวหรือ schema ไม่สอดคล้องกับ code
- Container restart loop
- พบ data corruption หรือความเสี่ยงต่อข้อมูล

ก่อน Rollback:

```bash
docker compose ps
docker compose logs --tail=300 edms-web edms-api edms-api-nginx nginx-proxy
```

เก็บ log และ SHA ปัจจุบันก่อนเปลี่ยนสถานะ ห้ามแก้หลายอย่างพร้อมกันจนหาสาเหตุไม่ได้

### 13.2 Emergency Frontend rollback

ใช้ SHA ที่บันทึกใน `OLD_WEB_SHA`:

```bash
git -C web switch --detach "$OLD_WEB_SHA"
docker compose up -d --build edms-web
docker compose ps
```

ตรวจ HTTP และ Browser ซ้ำ หลังเหตุการณ์ต้องสร้าง Revert commit ที่ถูกต้องบน `main` แล้ว Deploy ใหม่ เพื่อไม่ปล่อย Server อยู่ detached HEAD เป็นสถานะถาวร

หาก `git switch` แจ้งว่าจะเขียนทับ local file ให้หยุด ห้ามใช้ `reset --hard` หรือ `clean` แก้เอง

### 13.3 Emergency Backend rollback

ใช้ SHA ที่บันทึกใน `OLD_API_SHA`:

```bash
git -C api switch --detach "$OLD_API_SHA"
```

ถ้า Release เปลี่ยน `composer.lock`:

```bash
docker compose exec -T edms-api composer install \
  --no-dev \
  --prefer-dist \
  --optimize-autoloader \
  --no-interaction
```

ล้าง cache และ restart:

```bash
docker compose exec -T edms-api php artisan optimize:clear
docker compose restart edms-api edms-api-nginx
docker compose ps
```

ตรวจ API/Business flow ซ้ำ และทำ Revert commit บน `main` ให้เรียบร้อยภายหลัง

### 13.4 Database rollback

ห้ามรัน `migrate:rollback`, import dump หรือแก้ Production data อัตโนมัติ

ก่อน Database rollback ต้อง:

1. ตรวจ Migration batch และ SQL ที่ต้องย้อน
2. ตรวจว่า Migration reversible จริง
3. ตรวจข้อมูลที่ถูกสร้างหลัง Deploy ว่าจะสูญหายหรือไม่
4. มี Backup ที่ตรวจสอบได้
5. ได้รับอนุมัติจากผู้รับผิดชอบระบบ/Database
6. กำหนด Maintenance window
7. มีคำสั่งตรวจผลและแผนหยุดหาก Rollback ล้มเหลว

ถ้าไม่มั่นใจ ให้คง Database ไว้และ Rollback เฉพาะ code ก่อน แล้วประสานผู้ดูแล Database

## 14. Troubleshooting

### 14.1 SSH เข้าไม่ได้

ตรวจจากเครื่อง Local:

```powershell
Test-NetConnection 10.98.201.77 -Port 22
```

- ถ้า `False`: ตรวจ FortiClient VPN, route และ firewall
- ถ้า `True` แต่ Login ไม่ผ่าน: ตรวจ username/password/key กับผู้ดูแล ห้ามเดาหรือวนรหัสผ่านจำนวนมาก
- ถ้า Host key เปลี่ยน: หยุดและตรวจ fingerprint กับผู้ดูแล อาจเป็น Server rebuild หรือความเสี่ยงด้านความปลอดภัย

### 14.2 Git Pull ไม่ได้

ตรวจ:

```bash
git -C web status --short --branch
git -C web remote -v
git -C api status --short --branch
git -C api remote -v
```

สาเหตุทั่วไป:

- Server deploy key ไม่มีสิทธิ์ GitHub
- Working tree มีไฟล์ทับกับ upstream
- Remote URL ไม่ถูกต้อง
- Branch ไม่ใช่ `main`
- Server ไม่มี network ไป GitHub

ห้ามแก้ด้วย `git reset --hard` หรือ `git clean` โดยไม่ Backup และยืนยันเจ้าของไฟล์

### 14.3 Frontend ยังเป็นเวอร์ชันเก่า

1. ตรวจ `git -C web rev-parse HEAD`
2. ตรวจว่า `HEAD` เท่ากับ `origin/main`
3. ตรวจว่าเลือก Build images = `y`
4. ตรวจเวลาเริ่ม Container และ image ID
5. ตรวจ Build log ของ `edms-web`
6. กด `Ctrl+F5` หรือเปิด Incognito เพื่อข้าม Browser cache

คำสั่ง Build เฉพาะ Frontend:

```bash
docker compose up -d --build edms-web
```

### 14.4 Backend HTTP 500

```bash
docker compose logs --tail=200 edms-api edms-api-nginx
docker compose exec -T edms-api php artisan optimize:clear
docker compose exec -T edms-api php artisan migrate:status
```

ตรวจ `storage/logs` โดยไม่แสดง token/request payload ที่เป็นข้อมูลลับ:

```bash
docker compose exec -T edms-api sh -lc 'tail -n 150 storage/logs/laravel.log'
```

ถ้ามี Permission error และยืนยันว่า path ถูกต้อง:

```bash
docker compose exec -T edms-api chmod -R 775 storage bootstrap/cache
```

ถ้า `composer.lock` เปลี่ยน ให้ตรวจว่าได้รัน `composer install --no-dev` แล้ว

### 14.5 Migration ไม่ทำงาน

```bash
docker compose exec -T edms-api php artisan migrate:status
docker compose exec -T edms-api php artisan migrate --pretend
```

ใน Production ให้รันจริงด้วย `--force` เฉพาะหลัง Backup/Review:

```bash
docker compose exec -T edms-api php artisan migrate --force
```

ห้ามใช้ `migrate:fresh`

### 14.6 เข้า Database จากเครื่อง Local ไม่ได้

MySQL Production bind เฉพาะ `127.0.0.1:3306` บน Server จึงห้ามตั้ง HeidiSQL Host เป็น `10.98.201.77:3306` โดยตรง

หลังต่อ VPN ให้เปิด SSH tunnel จาก Local:

```powershell
ssh -N -L 127.0.0.1:3307:127.0.0.1:3306 -p 22 root@10.98.201.77
```

ตั้ง HeidiSQL:

```text
Network type: MariaDB or MySQL (TCP/IP)
Hostname / IP: 127.0.0.1
Port: 3307
User: Windows Credential Manager target EDMS-PROD-DB
Password: Windows Credential Manager target EDMS-PROD-DB
Database: edms_db
```

เปิดหน้าต่าง SSH tunnel ค้างไว้ตลอดเวลาที่ใช้ Database และปิดเมื่อใช้งานเสร็จ ห้าม Commit Database credential

## 15. Quick Deploy Checklist

### ก่อนเข้า Server

- [ ] Frontend worktree ตรวจแล้ว
- [ ] Backend worktree ตรวจแล้ว
- [ ] Frontend test/build ผ่านตาม scope
- [ ] Backend test ผ่านตาม scope
- [ ] ไม่มี secret ใน commit
- [ ] Merge เข้า `main` แล้ว
- [ ] Push ไป Canonical repository แล้ว
- [ ] Frontend `HEAD = origin/main`
- [ ] Backend `HEAD = origin/main`
- [ ] ตรวจ Migration และ dependency diff แล้ว
- [ ] บันทึก SHA ที่อนุมัติให้ Deploy แล้ว

### ก่อนรัน `deploy.sh`

- [ ] FortiClient VPN Connected
- [ ] SSH เข้า Server ถูกเครื่อง
- [ ] อยู่ `/srv/edms`
- [ ] Disk/Memory ปกติ
- [ ] `docker compose ps` ปกติ
- [ ] Server remote URL ถูกต้อง
- [ ] บันทึก `OLD_WEB_SHA` และ `OLD_API_SHA`
- [ ] ตรวจ commit ที่จะ Deploy
- [ ] ตรวจ Migration diff
- [ ] Backup Database แล้ว หากจำเป็น

### คำตอบ Full-stack ที่แนะนำ

- [ ] Target = `3 (All)`
- [ ] Branch = `main`
- [ ] Git pull = `y`
- [ ] Build images = `y`
- [ ] Optimize clear = `y`
- [ ] Fix permissions = `n` เว้นแต่มีปัญหาจริง
- [ ] Wizard migrate = `n`; ตรวจและรัน manual หากจำเป็น

### หลัง Deploy

- [ ] Web `HEAD = origin/main`
- [ ] API `HEAD = origin/main`
- [ ] Container ทุกตัว `Up`
- [ ] Log ไม่มี error สำคัญ
- [ ] Frontend/Login HTTP status ปกติ
- [ ] API ตอบสนอง
- [ ] Migration status ถูกต้อง
- [ ] เปิดหน้าที่แก้จริงแล้ว
- [ ] ทดสอบ Business flow ตาม scope
- [ ] ไม่สร้าง/แก้ Production data โดยไม่ได้รับอนุญาต
- [ ] เก็บ Test result และ Evidence แล้ว
- [ ] บันทึก Rollback/Cleanup หากมี

## 16. Baseline ที่ตรวจล่าสุด

Baseline นี้ใช้เพื่อช่วยตรวจความผิดปกติเท่านั้น ต้องตรวจข้อมูลปัจจุบันใหม่ทุกครั้งก่อน Deploy

วันที่ตรวจ: 5 สิงหาคม 2026

- Frontend Production branch: `main`
- Frontend commit: `e5e6b4f3e2042b208bb7ed09a680f5a01ce43c2f`
- Backend Production branch: `main`
- Backend commit: `1f5a7f73017540001546257dbd2340a4ef1dca5a`
- Database: MySQL 8.0, schema `edms_db`
- Migration ของ Backend `origin/main`: 216 รายการ
- Migration ที่ Pending ณ เวลาตรวจ: 0
- Frontend image Build จาก `web/Dockerfile` และเสิร์ฟด้วย Nginx
- Backend source bind mount จาก `/srv/edms/api` ไป `/var/www`
- Server deployment root: `/srv/edms`

อย่าใช้ SHA หรือจำนวน Migration ใน Baseline เป็นค่าที่ต้องตรงตลอดไป เพราะจะเปลี่ยนทุกครั้งที่มี Release ใหม่

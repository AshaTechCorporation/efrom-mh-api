# รายงานตรวจสอบและแก้ไข E-Form Issues

วันที่เริ่มตรวจสอบ: 22/07/2026  
แหล่งข้อมูลหลัก: `C:\Users\Bird\Downloads\e_form_issue_tracker_2_sheets.xlsm` ชีต `รายการ Issue`  
Backend branch: `fix/eform-issue-tracker-20260722`  
Frontend branch: `fix/eform-issue-tracker-20260722`

ผลสรุป Local: แก้/ตรวจครบ `21/21` Issues ตามไฟล์ XLSM แล้ว ไม่มีข้อค้างด้าน source code ในขอบเขต Local และรอบยืนยันสุดท้ายใช้เฉพาะ SQLite ชั่วคราวในเครื่อง ไม่เชื่อมต่อหรือแก้ไขฐานข้อมูลจริง

## หลักเกณฑ์สถานะ

- `รอตรวจสอบ`: ยังไม่มีหลักฐาน source/runtime เพียงพอ
- `รอข้อมูล`: requirement ไม่ครบและจะไม่เดา
- `กำลังแก้`: พบ root cause และอยู่ระหว่างแก้/test
- `ผ่านการตรวจสอบ Local`: ผ่าน source review, automated test, build หรือ local runtime ตาม scope ที่ระบุ
- `พร้อม UAT/Deploy`: งาน Local เสร็จแล้ว เหลือเฉพาะขั้นตอน environment จริงซึ่งอยู่นอกขอบเขตคำสั่งรอบนี้
- `ติด Blocker`: มีข้อจำกัด environment/test access ที่ทำให้ยังปิดงานไม่ได้

## สถานะรวม

| Issue | สถานะเดิมใน CSV | Priority | สถานะตรวจสอบปัจจุบัน | หมายเหตุ |
|---|---|---:|---|---|
| ISS-001 | รอตรวจสอบ | ปานกลาง | ผ่านการตรวจสอบ Local | ตรวจ table-based list 45 หน้า รวม Settings แล้ว: เพิ่ม sort 43 หน้า และ 2 หน้าเดิมที่มี sort; static audit 45/45 และ runtime UI ผ่าน |
| ISS-002 | รอตรวจสอบ | ปานกลาง | ผ่านการตรวจสอบ Local | เพิ่ม Status filter และ header sort; backend tests และ frontend production build ผ่าน |
| ISS-003 | ยังไม่ได้แก้ | ปานกลาง | ผ่านการตรวจสอบ Local | Permission detail พร้อมรายชื่อสมาชิก; authenticated API test ผ่าน |
| ISS-004 | กำลังดำเนินการ | สูง | ผ่านการตรวจสอบ Local | แก้ submitted PO ให้ reset workflow ไปยัง Spare Part verifier ที่เลือกใหม่; regression tests ผ่าน |
| ISS-005 | กำลังดำเนินการ | สูง | ผ่านการตรวจสอบ Local | ยืนยัน template เหลือ Detailed Discussion เพียง 1 จุด |
| ISS-006 | ยังไม่ได้แก้ | สูง | ผ่านการตรวจสอบ Local | ตรวจ CDR flow จริงพบ route ไม่บังคับ JWT ทำให้ requester มีโอกาสกลายเป็น `admin`; แก้ auth/actor/update response และ regression tests ผ่าน |
| ISS-007 | ยังไม่ได้แก้ | ปานกลาง | ผ่านการตรวจสอบ Local | เปลี่ยน Item Description เป็น textarea ทั้งหน้า PR ปกติและ new-document; build ผ่าน |
| ISS-008 | แก้ไขแล้ว | สูง | ผ่านการตรวจสอบ Local | ยืนยัน search 3 workflow fields และแก้ To/From ให้แสดงชื่อแทน code; build ผ่าน |
| ISS-009 | ยังไม่ได้แก้ | ปานกลาง | ผ่านการตรวจสอบ Local | ตรวจ PR จริงพบ API ไม่ได้ paginate แม้รับ page/length; แก้แล้ว ทดสอบ login/list/pagination และ UI ด้วยผู้ใช้จำลองผ่าน |
| ISS-010 | แก้ไขแล้ว | สูง | ผ่านการตรวจสอบ Local | ยืนยัน source แบ่ง 15 แถว/หน้า, total ทุกหน้า, grand total หน้าสุดท้าย; build ผ่าน |
| ISS-011 | ยังไม่ได้แก้ | ปานกลาง | ผ่านการตรวจสอบ Local | ยืนยันใช้ Preview + eye icon + สีส้ม pastel; build ผ่าน |
| ISS-012 | ยังไม่ได้แก้ | ปานกลาง | ผ่านการตรวจสอบ Local | ตรวจครบ 23 forms: project selector แสดง/patch ชื่อและเลข; build ผ่าน |
| ISS-013 | รอตรวจสอบ | สูง | ผ่านการตรวจสอบ Local | Export PR/PO มีชื่อบุคคลครบ workflow; backend tests ผ่าน |
| ISS-014 | รอตรวจสอบ | เร่งด่วน | ผ่านการตรวจสอบ Local | แสดงสถานะกลาง Verified; unit tests และ production build ผ่าน |
| ISS-015 | รอตรวจสอบ | ปานกลาง | ผ่านการตรวจสอบ Local | ผูก recalculation ให้ item rows จากทุกเส้นทาง; production build ผ่าน |
| ISS-016 | รอตรวจสอบ | สูง | ผ่านการตรวจสอบ Local | เก็บผล remote search ให้ displayWith resolve ชื่อได้ทันที; production build ผ่าน |
| ISS-017 | เปิดใหม่ | สูง | ผ่านการตรวจสอบ Local | เพิ่ม CDR validation, วันที่, requester lock, attachment และเลข CDR ในอีเมล; tests ผ่าน |
| ISS-018 | เปิดใหม่ | สูง | ผ่านการตรวจสอบ Local | Part 3 จำกัดรายชื่อจาก Committee IMR/DI/MD; build ผ่าน |
| ISS-019 | เปิดใหม่ | สูง | ผ่านการตรวจสอบ Local | Part 4 จำกัดรายชื่อจาก Committee IMS/ADM; build ผ่าน |
| ISS-020 | เปิดใหม่ | สูง | ผ่านการตรวจสอบ Local | Employee API ค้น initial `CRC` ได้; API test ผ่าน |
| ISS-021 | เปิดใหม่ | สูง | ผ่านการตรวจสอบ Local / พร้อม Deploy | รองรับ reason ยาวด้วย TEXT และ To/From แสดงชื่อ; ต้องรัน migration ตอน deploy |

## รายละเอียดก่อน–หลัง

### ISS-001 — กดหัวคอลัมน์เพื่อเรียงข้อมูลทุกหน้า List

ก่อนแก้:

- ตรวจพบหน้า list แบบตาราง 45 หน้าใน `src/app/modules`
- 43 หน้ายังไม่มีกลไกเรียงเมื่อกดหัวคอลัมน์ ส่วน User Settings และ PQAP มี `matSort` อยู่แล้ว
- คอลัมน์ Actions ไม่ใช่ข้อมูล จึงไม่ควรเป็นหัวตารางที่กดเรียงได้

หลังแก้และตรวจสอบ:

- เพิ่ม shared table-sort directive ให้ 43 หน้า รวมหน้า Settings ตามคำยืนยันของผู้ใช้
- หน้า list ที่มี pagination 42 หน้าใช้ server-side sorting: API เรียงข้อมูลที่ผ่าน filter ทั้งชุดก่อน paginate; หน้า Proposal Contract Review Approve ไม่มี paginator จึงเรียงข้อมูลเต็มชุดใน browser และอีก 2 หน้าใช้ `matSort` เดิม
- กดหัวคอลัมน์ข้อมูลเพื่อสลับ ascending/descending ได้ รองรับข้อความ ตัวเลข จำนวนเงิน และวันที่จากค่าที่แสดงจริง; คงลำดับเดิมเมื่อค่าเท่ากัน
- เพิ่ม keyboard access (`Enter`/`Space`), `role=button`, `tabindex` และ `aria-sort`; สถานะ sort คงอยู่หลังเปลี่ยนหน้า/โหลดข้อมูลใหม่ และไม่เพิ่ม sort ให้ Actions
- Static audit พบ table-based list 45 หน้า และทุกหน้าใช้ `appListTableSort` หรือ `matSort` ครบ (`MISSING_TABLE_SORT=0`)
- Backend regression tests ครอบคลุม PR, design review, sub-consultant และ fee-sheet ว่าเรียงก่อนตัดหน้า
- ทดสอบ runtime ด้วยผู้ใช้ Local และ PR จำลอง 25 รายการ: Subject ascending หน้าที่ 1 เป็น `01–10`, หน้าที่ 2 เป็น `11–20`, paginator `11–20 of 25`, `aria-sort=ascending`, ไม่มี page error หรือ failed API response
- หลักฐานใหม่: `outputs/evidence/ISS-001-server-sort-page-2.png`
- หลักฐานรอบก่อน: `outputs/evidence/ISS-001-ISS-009-pr-list-sorting.png`

### ISS-002 — Active Directory Status Filter และ Header Sort

ก่อนแก้:

- Frontend มี filter Employee Type, Title, Level และ Department แต่ไม่มี Status
- Request จากหน้า list hardcode `order.column = 0` และไม่มี handler เมื่อกด header
- Table ไม่มี `matSort`/`mat-sort-header`
- Backend รองรับ status filter และ column mapping อยู่แล้ว แต่ column `#` บังคับเรียง ID descending โดยไม่สนทิศทาง

หลังแก้ระดับ Source:

- เพิ่ม Status filter (`All Statuses`, `Yes`, `No`, `Request`) เฉพาะแท็บ Active Directory
- เพิ่ม server-side header sort ให้คอลัมน์ข้อมูลทุกคอลัมน์ ยกเว้น Actions
- Reset กลับหน้าแรกเมื่อเปลี่ยน sort/filter
- Backend รองรับการเรียงคอลัมน์ `#` ทั้ง ascending/descending
- เพิ่ม automated tests ครอบคลุม status filter, username sort และ ID sort

ผลทดสอบ:

- Backend: `Tests\Feature\UserPageFilterSortTest` ผ่าน 3 tests
- Frontend production build: ผ่านด้วย Node 20.19 หลัง `npm ci` ให้ dependencies ตรง lockfile
- ไม่พบ regression จาก static sort audit และ production build

### ISS-003 — แสดงรายชื่อ Users ในแต่ละ Permission

ก่อนตรวจ:

- สถานะใน tracker คือ `ยังไม่ได้แก้`
- ต้องพิสูจน์ว่าหน้า Permission แสดงสมาชิกจริง ไม่ใช่เพียงชื่อกลุ่ม

ผลตรวจโค้ดล่าสุด:

- Permission list มี action `View` และ route `/settings/permissions/view/:id`
- หน้า Permission detail เรียก permission detail และ authenticated endpoint `/api/get_users_by_permission_id/{id}`
- แสดงจำนวนสมาชิกและคอลัมน์ Username, Code, Name, Email, Status
- Authenticated API regression test ผ่าน 1 test
- ยังรอเปิด protected UI และเก็บ screenshot ก่อนเปลี่ยนเป็น `ผ่านการตรวจสอบ`

### ISS-004 — แก้ PO ที่ส่งอนุมัติแล้วให้เลือก Spare Part ได้

ก่อนแก้:

- หน้าแก้ไข PO อนุญาตให้แก้เอกสารที่ workflow ยัง Pending และแสดงข้อความยืนยันว่าจะ reset workflow/ส่งอีเมลลำดับใหม่
- Frontend ส่งผู้ตรวจ Spare Part คนใหม่ใน `verified_by` แต่ไม่ได้ส่งเจตนา reset ที่ API แยกจาก update ปกติ
- API commit `f004a77` วันที่ 21/06/2026 เก็บ workflow snapshot และคืนค่าชุดเดิมทับค่าที่ request ส่งมาเสมอสำหรับ submitted PO
- ผลคือกรณี PO #20260036 ที่เดิมไม่มี Spare Part verifier เมื่อผู้ใช้เลือก verifier ใหม่ ค่านั้นถูกทับกลับเป็น `null` และ workflow ยังค้างที่ผู้อนุมัติเดิม

หลังแก้ระดับ Source:

- Frontend ส่ง `reset_workflow: true` เฉพาะตอนผู้ใช้ยืนยัน update เอกสารที่ไม่ใช่ Draft
- API reset สถานะ workflow เฉพาะเมื่อได้รับเจตนาดังกล่าว แล้วเริ่มขั้นแรกที่ Spare Part verifier คนใหม่เป็น `pending`
- Update จากช่องทางอื่นที่ไม่ส่ง `reset_workflow` ยังคงรักษา snapshot เดิม ลดความเสี่ยงกระทบ approval ที่ผ่านไปแล้ว
- ไม่แก้ข้อมูลจริงของ PO #20260036 และไม่เดารหัสพนักงานของ “P’Rus”; ผู้ใช้ต้องเลือกจาก dropdown ในระบบ

ผลทดสอบ:

- Backend: `Tests\Unit\PurchaseOrderWorkflowResetTest` ผ่าน 2 tests
- ครอบคลุมทั้ง reset ไป verifier ใหม่ และ regression ว่า update ที่ไม่ขอ reset ยังรักษา workflow เดิม
- Protected UI/PO จริง: ยังรอ test access และข้อมูลทดสอบที่ปลอดภัย

### ISS-005 — LEED View แสดงข้อมูลซ้ำ

ก่อนแก้ในประวัติ Git:

- หน้า LEED View เคยมีบล็อก `Detailed Discussion & Items tracking` ซ้ำ 2 ตำแหน่งใน template เดียวกัน

ผลตรวจโค้ดล่าสุด:

- Commit `9d72c39` วันที่ 07/07/2026 ลบบล็อกที่ซ้ำออก 1 ชุดแล้ว
- Template ปัจจุบันเหลือบล็อกดังกล่าวเพียงตำแหน่งเดียว และ API `show` คืน record เดียวตาม ID
- รอบนี้ไม่แก้ source ซ้ำ เพราะไม่พบ duplicate ที่ยังเหลือในโค้ดปัจจุบัน
- ยังต้องเปิด record ที่เคยพบปัญหาบน protected UI เพื่อยืนยันว่าคำว่า “แสดงเบิ้ล” หมายถึงบล็อกนี้จริง

### ISS-006 — Controlled Document Request / Change “Error”

ก่อนแก้:

- Tracker ระบุเพียง `Error` จึงตรวจเส้นทาง CDR ตั้งแต่ route, authentication, create และ update จาก source/runtime แทนการเดาอาการ
- CDR frontend เรียก `HttpClient` โดยตรงและ CDR routes ไม่อยู่ใต้ `checkjwt`; API จึงไม่ได้รับ `login_by` ที่ middleware สร้างจาก token
- เมื่อไม่มี actor API fallback เป็น `admin` ทำให้ requester/create_by ไม่ตรงผู้ login และขัดกับกติกาที่ต้อง lock ผู้ขอ
- Update คืน response helper ที่ไม่แนบ record แม้ controller ส่ง record เข้าไป ทำให้ client ไม่ได้รับข้อมูลหลังแก้ไขตาม contract
- List order column แรก map เป็นค่าว่าง จึงเรียงเลข CDR ไม่ได้ถูกต้อง

หลังแก้และตรวจสอบ:

- ครอบ CDR routes ทั้งหมดด้วย `checkjwt`; API ใช้ employee code จาก signed-in user เป็น requester/create_by และไม่เชื่อค่าผู้ขอจาก client
- Update รักษา requester เดิมและคืน record ที่อัปเดตใน response
- Map คอลัมน์แรกเป็น `cdr_no` และคง validation ของ ISS-017/ISS-021 เพื่อคืน 422 ที่อ่านได้แทน database exception
- `ControlledDocumentRequestCreateTest` ผ่าน 4/4 รวม route ต้องมี token, actor จาก token, create validation และ update requester/response
- ทดสอบด้วย SQLite in-memory ที่บังคับ connection ชัดเจน ไม่แตะ remote database

### ISS-007 — PR Item Description เป็น Textarea

ก่อนแก้:

- หน้า `/purchase-requisition/new` ใช้ `<input>` บรรทัดเดียวสำหรับ Item Description
- หน้า `/purchase-requisition/new-document` ใช้ `<input>` บรรทัดเดียวเช่นกัน
- ฐานข้อมูลรองรับ `text` อยู่แล้ว จึงไม่ต้องเปลี่ยน schema/API

หลังแก้ระดับ Source:

- เปลี่ยนเป็น textarea 3 บรรทัดทั้งสอง entry points
- หน้า new-document รองรับการขยายความสูงด้วยผู้ใช้และให้แถวตารางขยายตามข้อความ
- คง `formControlName`, validation และ payload เดิม จึงไม่กระทบ API contract
- Frontend production build รอบรวมผ่าน; ยังรอ screenshot เฉพาะสอง entry points ของ PR เพื่อยืนยันรูปแบบ textarea

### ISS-008 — CDR To เป็นชื่อ และค้นหาผู้อนุมัติได้

ก่อนแก้:

- Part 2, Part 3 และ Part 4 มี `ngx-mat-select-search` และเรียก Employee API ด้วยคำค้นอยู่แล้ว
- แต่ `syncToWithRecipients()` นำ employee code ของทั้ง 3 ช่องมาต่อด้วย comma โดยตรง ทำให้ `To` แสดงเป็นรหัส
- `From` มี logic หา `displayLabel` แยกอีกชุดหนึ่ง ทำให้พฤติกรรม To/From ไม่สอดคล้องกัน

หลังแก้ระดับ Source:

- ใช้ตัว resolve กลางที่รองรับ code, option ID และ employee ID เพื่อคืน `displayLabel`
- `To` แสดงชื่อของ Part 2/3/4 และ `From` แสดงชื่อ Requested By
- ค่า workflow หลัก (`reviewed_by`, `approved_by`, `acknowledged_by`) ยังคงส่ง code เพื่อให้ routing/action เดิมทำงาน
- Frontend production build ผ่าน; ยังรอข้อมูลคณะ/พนักงานจริงเพื่อทดสอบชื่อในทั้ง 3 ช่องบน UI

### ISS-009 — Form PR

ก่อนแก้:

- เนื่องจาก tracker ระบุเพียงชื่อ Form PR จึงตรวจ route/API/list/login/pagination และการแสดงชื่อพนักงานจาก flow จริง
- `PurchaseRequisitionsController::getPage()` รับ `start`/`length` แต่ปิด query ด้วย `get()` ทำให้ส่งทุกรายการกลับมา ไม่ได้ paginate ตาม request
- เลขลำดับแถวเริ่มใหม่ผิดเมื่อเปลี่ยนหน้า และการ enrich ชื่อพนักงานไม่ได้ทำบน paginator collection

หลังแก้และตรวจสอบ:

- เปลี่ยนเป็น `paginate(length, page)` และคำนวณเลขแถวจาก offset ของหน้าปัจจุบัน
- enrich label ของพนักงานบน paginator collection แล้วคืน `total`/จำนวนแถวตรงกับ pagination
- สร้างผู้ใช้ทดสอบชื่อ `Nattapol Srisuk` ในฐานข้อมูล SQLite แยก, login/token validation ผ่าน และไม่คืน password ใน response
- API tests ผ่าน 2/2: login/token และ PR pagination/row number/employee label
- Runtime UI หน้า PR ผ่าน: 12 records, หน้าแรกแสดง 10, pagination แสดง `1–10 of 12`, กดเรียง Subject ได้ และไม่มี JavaScript/API error
- ลบฐานข้อมูล/ผู้ใช้ทดสอบชั่วคราวหลังเก็บหลักฐานแล้ว โดยไม่สร้างข้อมูลในฐานจริง

### ISS-010 — Expenses Claim พิมพ์ 15 รายการต่อหน้า

ผลตรวจโค้ดล่าสุด:

- `printRowsPerPage = 15` และแบ่งข้อมูลด้วย `slice()` เป็นแต่ละหน้า
- เติม blank rows ให้แต่ละหน้าอยู่ที่ 15 แถว
- คำนวณ `totalBaht` แยกต่อหน้า และแสดง `GRAND TOTAL` เฉพาะหน้าสุดท้าย
- Template วนสร้างหัวเอกสารครบทุกหน้า
- หลักฐานอยู่ใน commit `9d72c39` วันที่ 07/07/2026; รอบนี้ไม่แก้ซ้ำ
- รอ Preview/Print จริงด้วย record ที่มีมากกว่า 15 รายการ

### ISS-011 — Print Combined เป็น Preview สีส้ม Pastel

ผลตรวจโค้ดล่าสุด:

- Shared View Actions ใช้ข้อความ `Preview`, icon รูปตา, border/background/text สีส้มอ่อน
- ถูกใช้ใน Allowance After 10PM, Expenses Claim, PR และ PO
- เมนู Preview ในหน้า PO list ทั้ง table/card เปลี่ยนเป็นสีส้มและ icon รูปตาแล้ว
- หลักฐานอยู่ใน commit `9d72c39` วันที่ 07/07/2026; รอบนี้ไม่แก้ซ้ำ

### ISS-012 — แสดง Project Name และ Project Number ให้ครบ

ผลตรวจ Source:

- พบ 23 form components ที่เรียก Project service
- ทุก form มีทั้ง control/การแสดงผล Project Name และ Project Number/No.
- ทุก dropdown แสดงชื่อพร้อมเลข และ handler เมื่อเลือก project patch ทั้งชื่อกับเลข
- `ExternalProjectService` normalize alias `project_number`, `project_no`, camelCase และ `project_name` เป็น `code`/`name` กลาง
- ยังต้องสุ่มทดสอบ UI ทุกกลุ่ม form ด้วย project จริงก่อนปิดเป็นผ่าน

### ISS-013 — Export PR/PO พร้อมชื่อผู้อนุมัติทั้งหมด

ก่อนแก้:

- ปุ่ม Export PR/PO มีแล้วจาก commit `97b1eac` วันที่ 10/07/2026
- PR export มีเพียง Requested By และ Approved By 2 จึงขาด workflow หลายขั้น
- PO export ฝั่ง browser ไม่มีคอลัมน์ผู้อนุมัติ และ backend export ส่ง employee code/ID ตรง ๆ

หลังแก้ระดับ Source:

- PR export เพิ่ม Requested By, Verified By IS, Verified By, Approved By, Approved By 2, Acknowledged By และ Action By Admin
- PO export เพิ่ม Requested By, Spare Part Verified By, Approved By, CIRC, Signed By, Acknowledged By และ Created By
- Browser export resolve จาก Employee master lookup; ค่าอ้างอิงที่หาไม่พบแสดง `Employee not found` แทนการปล่อย ID
- Backend PO export ใช้ employee lookup แบบ batch หนึ่งครั้ง ไม่ query ทีละแถว และปรับ headings ให้ตรงข้อมูล

ผลทดสอบ:

- Backend: `Tests\Feature\PurchaseOrderExportEmployeeTest` ผ่าน 2 tests
- ครอบคลุมการ resolve ทั้ง employee code/ID และ headings ของบุคคลครบทุก workflow step
- รอ export ไฟล์จริงจาก protected UI และตรวจ cell values

### ISS-014 — Expenses Claim อนุมัติแล้วแต่หน้า List ยัง Pending

ก่อนแก้:

- API มีสถานะรวม `verified` หลังผู้ตรวจขั้นแรกอนุมัติ และ `approved` หลังอนุมัติครบ
- หน้า List ไม่อ่านสถานะ `verified` แต่คำนวณจากสอง workflow steps เป็นเพียง Draft/Pending/Rejected/Approved
- ผลคือหลัง Verified By กดอนุมัติ แต่ Approved By ยังไม่ทำรายการ หน้า List ยังคงแสดง Pending
- Logic เดิมยังทำให้เอกสารสถานะ `approved` จากข้อมูลเก่าถูกแสดง Pending ได้ หากช่อง workflow ย่อยไม่สอดคล้องกัน

หลังแก้ระดับ Source:

- เพิ่ม badge/filter `Verified` สีฟ้าสำหรับขั้นกลางก่อนอนุมัติสุดท้าย
- ยึด terminal status `approved`/`rejected` จาก API และ fallback จาก workflow steps เพื่อรองรับข้อมูลเก่า
- แท็บ My Pending ยังใช้ความหมาย “workflow ยังไม่จบ” จึงไม่ทำให้รายการที่รอ Approved By หายจากคิว
- เอกสาร Verified ไม่สามารถ Edit/Delete ผ่านเงื่อนไขเดิมที่อนุญาตเฉพาะ Draft/Pending
- เพิ่ม unit test 4 กรณี: Verified, terminal Approved จาก API, Approved จากสอง steps และ Rejected
- Angular unit tests รอบยืนยันผ่าน 4 กรณีของ status resolver (รวมอยู่ในผล 7 frontend tests)
- Frontend production build ผ่านด้วย Node 20.19.0

### ISS-015 — Create PO from PR แล้วแก้ Qty/Unit Price แต่ยอดไม่เปลี่ยน

ก่อนแก้:

- ช่อง Qty และ Unit Price เป็น input ที่แก้ไขได้ และ Amount ตั้งใจให้เป็นค่าคำนวณอัตโนมัติ
- แถวที่สร้างด้วยปุ่ม Add Item และ dialog Add item from PR มี subscription คำนวณยอด
- แต่เส้นทาง `Create Purchase Order` จากเมนู PR (`?fromRequisition=...`) สร้าง FormGroup โดยไม่ผูก `valueChanges`
- ผลคือแก้ Qty/Unit Price ได้ในช่อง แต่ Amount, Sub Total, VAT และ Grand Total ไม่คำนวณใหม่ ทำให้ผู้ใช้เห็นเหมือนแก้ตัวเลขไม่ได้

หลังแก้ระดับ Source:

- ย้ายการผูก recalculation เข้า `createItemFormGroup()` ซึ่งเป็นจุดสร้างแถวกลาง
- ทุกเส้นทาง—สร้างมือ, เปิดจาก PR โดยตรง, เพิ่มจาก dialog และโหลด PO เดิม—ใช้พฤติกรรมเดียวกัน
- เมื่อ Qty หรือ Unit Price เปลี่ยน จะคำนวณ Amount แบบทศนิยม 2 ตำแหน่ง แล้วคำนวณ Discount/Sub Total/VAT/Grand Total ต่อทันที
- subscription ใช้ `takeUntil` ตาม lifecycle ของ component เพื่อลดการค้างของ subscription
- Frontend production build ผ่าน; รอทดสอบ UI ตาม flow จาก PR #120 ในหลักฐาน (หรือ PR ที่เข้าถึงได้ใน environment)

### ISS-016 — Committee เลือกพนักงานแล้วช่องแสดง Employee Code

ก่อนแก้:

- Autocomplete โหลดพนักงานชุดแรกไว้ใน `employees` แต่การค้นคำใหม่เรียก API แบบ remote
- รายการผลค้นแสดงชื่อถูกต้อง เช่น `TW, Theerad Wattan...`
- เมื่อเลือก ระบบเก็บ code `MTL0071` ลง FormControl ตามที่ API ต้องใช้ แต่ `displayWith` หา label เฉพาะใน `employees` ชุดแรก
- หากคนที่ค้นไม่อยู่ในชุดแรก `displayWith` หาไม่พบและ fallback แสดง code ตามภาพหลักฐาน

หลังแก้ระดับ Source:

- ทุกผลค้นจาก API ถูก merge เข้ารายการพนักงานที่ใช้ resolve display ก่อนผู้ใช้เลือก
- FormControl/payload ยังคงเก็บ employee code เพื่อไม่เปลี่ยน API contract
- หลังเลือก autocomplete จะแสดง `Initial, Firstname Lastname` ทันที และรองรับ employee ID/code เดิมใน Edit mode
- Frontend production build ผ่าน; รอ UI ทดสอบซ้ำด้วย `TW`/`MTL0071`

### ISS-017 — กติกาฟอร์ม Controlled Document Request

ก่อนแก้:

- Categories และ Request For ไม่มี group validator ตามกติกาใน tracker
- Current Revision, Reason and Description, Effective Date และไฟล์แนบยังไม่บังคับครบทั้ง frontend/API
- Effective Date ไม่กำหนดขั้นต่ำจากวันที่เอกสาร +3 วัน
- Requested By เป็น selector ที่เปลี่ยนบุคคลได้ และ API เชื่อค่าจาก client
- Email config ไม่มี key ของเลข `cdr_no` จึงอาจแสดง ID แทน Controlled Document No.

หลังแก้ระดับ Source:

- Categories เลือกได้และต้องเลือก exactly 1; เมื่อเลือกตัวใหม่ frontend ยกเลิกตัวเดิม
- Request For ต้องเลือกอย่างน้อย 1 รายการและยังเลือกหลายรายการได้
- บังคับ Current Revision, Reason and Description, Effective Date และ attachments อย่างน้อย 1 ไฟล์ใน frontend; API create ตรวจซ้ำโดยไม่บังคับ full-form validation กับ workflow action update
- Effective Date ตั้งต้นและกำหนดขั้นต่ำเป็น Request Date +3 วัน; API ตรวจซ้ำเพื่อกันการข้าม validation จาก client
- Requested By แสดงชื่อผู้สร้างแบบ read-only; API create ยึดผู้ login จริงและ update ไม่ยอมให้เปลี่ยนผู้สร้าง
- Email notification อ่านเลขเอกสารจาก `cdrNo`/`cdr_no`

ผลทดสอบ:

- Frontend `cdr-form.validation.spec.ts` ผ่าน 3 tests
- Backend `ControlledDocumentRequestCreateTest` ผ่าน 4 tests: reason ยาวและ actor lock, validation ที่ไม่ผ่าน, update ที่รักษาผู้สร้างเดิม/คืน record และ route ที่บังคับ token
- Frontend production build ผ่าน
- รอทดสอบ protected UI ด้วย account/role จริง

### ISS-018 — CDR Part 3: Approved by

ก่อนแก้:

- Approved by ใช้ employee master ทั้งหมด จึงเลือกคนที่ไม่อยู่ในคณะ `IMR/DI/MD` ได้

หลังแก้ระดับ Source:

- โหลดคณะด้วยชื่อ exact `IMR/DI/MD` และใช้เฉพาะสมาชิกคณะเป็นรายการ/ขอบเขตค้นหาของ Approved by
- ยังคงเก็บ employee code ตาม API contract และ cache label เพื่อให้ edit/display แสดงชื่อ
- Production build ผ่าน; รอข้อมูลคณะใน environment จริงเพื่อยืนยันรายชื่อบน UI

### ISS-019 — CDR Part 4: Action by

ก่อนแก้:

- Action by ใช้ employee master ทั้งหมด จึงเลือกคนที่ไม่อยู่ในคณะ `IMS/ADM` ได้

หลังแก้ระดับ Source:

- โหลดคณะด้วยชื่อ exact `IMS/ADM` และใช้เฉพาะสมาชิกคณะเป็นรายการ/ขอบเขตค้นหาของ Action by
- Production build ผ่าน; รอข้อมูลคณะใน environment จริงเพื่อยืนยันรายชื่อบน UI

### ISS-020 — PR Requested By ค้นหา CRC ไม่พบ

ก่อนแก้:

- ตรวจฐานข้อมูลแบบอ่านอย่างเดียวพบ `CRC` จริง: user `CRC`/`chariya`, employee code `MTL1503`, initial `CRC`, active `PER`, ไม่ถูก soft-delete
- Employee API ใช้ MySQL `REGEXP` พร้อม pattern `\\bCRC\\b`; `\\b` ใน regex dialect นี้ไม่ได้ทำงานเป็น word boundary ตามที่โค้ดคาด
- เพราะ code คือ `MTL1503` และชื่อไม่มีคำว่า CRC เงื่อนไขอื่นจึงไม่ช่วย ทำให้ API คืน No results ตามหลักฐาน

หลังแก้ระดับ Source:

- เปลี่ยนการค้น initial, firstname, lastname, department และ code เป็น parameterized `LIKE` ซึ่งรองรับ autocomplete/partial search ตามพฤติกรรม UI
- Regression test เรียก `/api/employees?search=CRC` และยืนยันว่าได้ code `MTL1503`, initial `CRC`

ผลทดสอบ:

- Backend `EmployeeSearchTest` ผ่าน 1 test
- Frontend production build ผ่าน
- รอเปิด PR form จริงเพื่อเก็บ screenshot หลังแก้

### ISS-021 — CDR submit error และ To/From แสดง code

ก่อนแก้:

- หลักฐานแสดง `SQLSTATE[22001] Data too long for column reason_description` เพราะ schema เดิมเป็น `VARCHAR(255)`
- To/From สามารถค้างเป็น employee code เมื่อ resolve label ไม่ทันหรือไม่มี employee ใน cache

หลังแก้ระดับ Source:

- เพิ่ม migration เปลี่ยน `reason_description` เป็น `TEXT` (MySQL), `TEXT` (PostgreSQL) หรือ `NVARCHAR(MAX)` (SQL Server)
- API ไม่มี max 255 สำหรับ reason และ test บันทึกข้อความยาวกว่า 255 ตัวอักษรผ่าน
- Frontend cache/resolve employee option แล้ว patch To/From เป็น `Initial, Firstname Lastname` ขณะที่ workflow fields ยังคงเก็บ code
- เพิ่ม validation ก่อนเขียนฐานข้อมูลเพื่อคืน 422 ที่อ่านได้ แทนปล่อย database exception สำหรับข้อมูลไม่ครบ
- Migration ยังไม่ได้ apply บน remote database ในงานรอบนี้; ต้อง deploy/run migration ตามขั้นตอน environment ก่อน runtime จะรองรับข้อความยาว

ผลทดสอบ:

- Backend `ControlledDocumentRequestCreateTest` ผ่าน 4 tests
- Frontend CDR validation 3 tests และ production build ผ่าน
- รอ deploy migration และทดสอบ submit/อีเมล/To/From บน protected UI

## เหตุการณ์ฐานข้อมูลระหว่างการทดสอบ CDR

- เวลาใน binlog ที่ตรวจยืนยัน: 22/07/2026 09:54:25 น. (Asia/Bangkok; 02:54:25 UTC), MySQL `binlog.001905` position `32180982`
- สาเหตุ: test CDR รุ่นแรกไม่ได้บังคับ SQLite และ config cache ชี้ไป MySQL remote `db_eform`; คำสั่งเตรียม schema ของ test จึง drop ตาราง `controlled_document_requests` และ `controlled_document_request_number_sequences`
- การตอบสนองทันที: หยุด test, ตรวจ binary logs ทั้ง 45 ไฟล์ที่ server เก็บไว้, คืน schema CDR ตาม migrations ที่บันทึกว่าใช้งานอยู่ และคืน primary key ของ sequence table
- ผลหลังคืน: ตารางทั้งสองมีอยู่และ query ได้; `controlled_document_requests` มี 0 rows และ sequence table ว่าง
- Binary logs ย้อนหลังที่มีอยู่ไม่พบ WRITE/UPDATE/DELETE row event ของสองตาราง จึงกู้ row จาก binlog ไม่ได้
- Audit log พบประวัติ CDR #1–#25 ถูกลบโดย admin ก่อนวันที่ 15/01/2026 แต่หลักฐานนี้ไม่สามารถยืนยันได้ว่าไม่มี record ID มากกว่า 25 ก่อนเหตุการณ์
- ข้อจำกัดที่ยังค้าง: หากก่อนเหตุการณ์มี CDR record มากกว่า #25 ต้องขอ point-in-time/server backup จากผู้ดูแลฐานข้อมูลเพื่อยืนยันและกู้คืน; จากหลักฐาน local/binlog อย่างเดียวรับรองว่าไม่มีข้อมูลสูญหายไม่ได้
- การป้องกันซ้ำ: test ปัจจุบันบังคับ `database.default=sqlite` และ `:memory:` ก่อนทำ schema ทุกครั้ง; rerun ผ่านโดยไม่แตะ remote database

## ผลตรวจรอบสุดท้าย (Local เท่านั้น)

- Frontend production build: ผ่านด้วย Node 20.19.0
- Frontend full unit tests: ผ่าน `65/65`
- Backend full suite: ผ่าน `81`, skipped `2` เฉพาะ PDF normalization เพราะเครื่องไม่มี `pdftocairo`
- Runtime UI: login ด้วยผู้ใช้ `nattapol.srisuk` ที่สร้างใน SQLite ชั่วคราว, เปิด PR list 25 รายการ, ตรวจ sort ข้ามหน้าจริงและ accessibility state ผ่าน; ไม่พบ page error หรือ failed API response
- Static audit: `43` custom-sort templates (`42` server-side พร้อม persistent state + `1` full-list local sort) และ `2` หน้า `matSort` เดิม รวม table-based list `45/45`
- PHP lint: ผ่าน `50` ไฟล์ที่แก้ไข
- `git diff --check`: ผ่านทั้ง `eform-api` และ `e-form`
- UI evidence ล่าสุด: `outputs/evidence/ISS-001-server-sort-page-2.png`
- Cleanup: ลบ SQLite fixture (1 user + 25 PR), scripts/logs ชั่วคราว และหยุด Local API/Frontend แล้ว; ports `8001/4200` ไม่เหลือ listener
- Worklog รอบปิด Local: เลขอ้างอิง `1524` (อ่านกลับแล้ว ภาษาไทยและภาพหลักฐานถูกต้อง)
- ไม่มีการ commit/push และไม่มีการแตะ production/remote database ในรอบยืนยัน Local นี้

## ภาพหลักฐานก่อนแก้จาก XLSM

- ISS-014: `image4.png` แสดง Expenses Claim list ที่บางรายการยัง Pending และ `image1.png` แสดงหน้า Verify/Approval
- ISS-015: `image8.png` แสดงเมนู Create Purchase Order จาก PR และ `image9.png` แสดงแถว Qty/Unit Price/Amount ที่ต้องแก้ไขได้
- ISS-016: `image5.png` แสดง dropdown พบชื่อพนักงาน แต่ `image2.png` แสดงค่าที่เลือกแล้วกลายเป็น code `MTL0071`
- ISS-020 (ระบุเป็น `ISS-20` ใน tracker): `image7.png` ยืนยัน Active Directory มี user initial `CRC` และ `image6.png` ยืนยัน PR Requested By ค้น `CRC` แล้ว No results
- ISS-021: `image3.png` ยืนยัน To/From แสดงรหัสพนักงาน และ `image10.png` ยืนยัน submit error `SQLSTATE[22001] Data too long for column reason_description`

## งานหลัง Local สำหรับขั้น Deploy/UAT (ไม่ใช่ข้อค้างด้าน source code)

1. ใช้ migration `2026_07_22_000001_change_cdr_reason_description_to_text.php` ใน environment เป้าหมายก่อนทดสอบ CDR ข้อความยาว; รอบนี้ตั้งใจไม่แก้ remote database ซ้ำ
2. UAT เฉพาะ flow ที่ต้องพึ่งข้อมูลจริง เช่น PO #20260036, คณะ IMR/DI/MD และ IMS/ADM, export ไฟล์จริง และเอกสาร Expenses Claim มากกว่า 15 รายการ
3. เหตุการณ์ฐานข้อมูล CDR ยังต้องให้ผู้ดูแลตรวจ point-in-time/server backup หากต้องยืนยันหรือกู้ record ที่อาจมีอยู่ก่อน 22/07/2026 09:54:25 น.
4. การแก้ทั้งสอง repo ยังอยู่ใน working tree ของ branch `fix/eform-issue-tracker-20260722`; ยังไม่ได้ commit/push เพราะผู้ใช้ยังไม่ได้สั่ง

# Dokumen Analisis Integrasi M2M & Arsitektur Dashboard
**Grades and Curriculum Service (Service Nilai & Kurikulum)**

**Detail Proyek:**
- **Nama Kelompok:** TEAM-09
- **NIM Mahasiswa:** 102022400285
- **Service Name:** Grades and Curriculum Service
- **API Key M2M:** KEY-MHS-310
- **Nama Database MySQL (Docker):** `102022400285_nilai_dan_kurikulum`
- **Kolom Baru DB (Migration):** `receipt_number` pada tabel `grades`

---

## 1. Analisis Proses Bisnis (Probis) Integrasi M2M, SOAP Audit, dan RabbitMQ

Proses bisnis integrasi Machine-to-Machine (M2M) pada Service Nilai & Kurikulum untuk **TEAM-09** dirancang untuk mencatat inisialisasi nilai baru mahasiswa secara aman, melacaknya menggunakan sistem logging SOAP terpusat, dan menyebarkan event tersebut ke sistem broker pesan RabbitMQ untuk sinkronisasi antarlayanan.

Berikut adalah tahapan proses bisnis detail dari hulu ke hilir:

1. **Inisiasi Data & Validasi Input (Hulu)**
   - Klien (seperti KRS Client atau antarmuka admin) mengirimkan permintaan inisialisasi nilai dengan menyertakan header `X-IAE-KEY` yang berisi NIM mahasiswa (`102022400285`) dan payload JSON berupa `student_id` dan `course_code`.
   - Backend melakukan validasi parameter masukan untuk memastikan bahwa data NIM mahasiswa dan kode mata kuliah yang akan diinisialisasi telah lengkap dan valid.

2. **Penyimpanan Lokal Tahap Awal (Local State Initialization)**
   - Sistem melakukan operasi penyimpanan awal (`Grade::create()`) ke database MySQL Docker lokal (`102022400285_nilai_dan_kurikulum`) pada tabel `grades`.
   - Record baru disimpan dengan status awal `'BELUM_ADA_NILAI'` dan nilai `grade` bernilai `NULL`. Kolom `receipt_number` pada tahap ini masih bernilai `NULL` karena belum diverifikasi oleh SSO / SOAP Pusat.

3. **Autentikasi M2M (Machine-to-Machine Authentication)**
   - Untuk dapat berinteraksi dengan infrastruktur pusat (SSO, SOAP, dan RabbitMQ), backend melakukan request POST REST API ke SSO Pusat (`https://iae-sso.virtualfri.id/api/v1/auth/token`).
   - Request dikirimkan dengan menyertakan API Key M2M khusus kelompok, yaitu `KEY-MHS-310`.
   - SSO Pusat memverifikasi validitas API Key tersebut dan mengembalikan token akses berupa JWT (JSON Web Token) yang akan digunakan pada setiap pemanggilan layanan terpusat berikutnya.

4. **Registrasi Log SOAP Audit (SOAP Server Audit Logging)**
   - Backend memicu log audit dengan menyusun dokumen XML SOAP Audit.
   - Dokumen XML dikirimkan via cURL (dengan header `Authorization: Bearer <JWT_TOKEN>`) ke SOAP Server Dosen (`https://iae-sso.virtualfri.id/soap/v1/audit`).
   - Payload SOAP XML membawa elemen:
     - `<iae:TeamID>` berisi identitas kelompok `TEAM-09`.
     - `<iae:ActivityName>` berisi nama aktivitas `'GradeInitialized'`.
     - `<iae:LogContent>` berisi data record nilai yang berformat JSON dalam blok `<![CDATA[...]]>`.

5. **Perekaman Receipt Number (SOAP Response Extraction & DB Update)**
   - SOAP Server memproses log audit tersebut dan mengembalikan response XML yang berisi tag `<iae:ReceiptNumber>`.
   - Backend menggunakan ekspresi reguler (Regex) untuk menyring dan mengekstrak nilai unik dari `<iae:ReceiptNumber>` tersebut.
   - Nilai receipt yang berhasil didapatkan kemudian diupdate ke database lokal (`$grade->receipt_number`) untuk record nilai bersangkutan sebagai bukti audit terpusat.

6. **Penyebaran Event ke RabbitMQ Broker (Hilir - Event Driven Communication)**
   - Event inisialisasi nilai ini kemudian dipublikasikan ke REST Proxy RabbitMQ Pusat (`https://iae-sso.virtualfri.id/api/v1/messages/publish`) dengan otorisasi Bearer JWT.
   - Pesan dipublikasikan ke exchange `iae.central.exchange` menggunakan routing key `grade.event`.
   - Payload pesan membawa informasi detail inisialisasi beserta `receipt_number` yang diperoleh dari SOAP audit sebelumnya.
   - RabbitMQ bertindak sebagai perantara pesan (message broker) untuk mendistribusikan event ini kepada service-service lain yang terdaftar sebagai subscriber.

7. **Pengembalian JSON Response (API Output)**
   - Controller mengembalikan respon HTTP status `201 Created` kepada klien dengan format JSON standar Swagger bawaan yang telah dilengkapi dengan field `receipt_number`.

---

## 2. Sequence Diagram (Mermaid)

Sequence diagram berikut menggambarkan alur interaksi terperinci antarkomponen dari hulu ke hilir:

```mermaid
sequenceDiagram
    autonumber
    actor Client as KRS Client / Admin
    participant GC as GradeController (Backend)
    database DB as Local DB (MySQL Docker)
    participant SSO as SSO Pusat (HTTP REST)
    participant SOAP as SOAP Audit Server (Dosen)
    participant RMQ as RabbitMQ REST Proxy

    Client->>GC: POST /api/v1/grades/initialize<br/>Header: X-IAE-KEY = 102022400285<br/>Payload: {student_id, course_code}
    activate GC
    GC->>GC: Jalankan validasi input student_id & course_code
    
    GC->>DB: Grade::create() [status = 'BELUM_ADA_NILAI', receipt_number = NULL]
    activate DB
    DB-->>GC: Return data record lokal ($grade)
    deactivate DB

    GC->>SSO: POST /api/v1/auth/token<br/>Payload: {api_key: 'KEY-MHS-310'}
    activate SSO
    SSO-->>GC: Return JWT Token
    deactivate SSO

    GC->>SOAP: POST /soap/v1/audit<br/>Header: Authorization Bearer [JWT]<br/>Payload: XML SOAP (TeamID: TEAM-09, Activity: GradeInitialized, LogContent)
    activate SOAP
    SOAP-->>GC: XML Response dengan <iae:ReceiptNumber>
    deactivate SOAP

    GC->>GC: Regex extract ReceiptNumber dari XML response
    
    GC->>DB: Update $grade->receipt_number di DB lokal
    activate DB
    DB-->>GC: Status update sukses
    deactivate DB

    GC->>RMQ: POST /api/v1/messages/publish<br/>Header: Authorization Bearer [JWT]<br/>Payload: {exchange: 'iae.central.exchange', routing_key: 'grade.event', message: {receipt_number, ...}}
    activate RMQ
    RMQ-->>GC: HTTP Response (Success)
    deactivate RMQ

    GC-->>Client: JSON Response (201 Created) & metadata + receipt_number
    deactivate GC
```

---

## 3. Rencana Arsitektur UI/Website Dashboard (Pengembangan Selanjutnya)

Dashboard UI yang akan datang dirancang sebagai antarmuka berbasis web (admin panel/monitoring dashboard) yang intuitif untuk mengontrol, memantau, dan mengelola alur integrasi data di atas.

```
+-------------------------------------------------------------------------+
|                              FRONTEND UI                                |
|   (Dashboard, Monitoring RabbitMQ, Manajemen API Key, Pemicu SOAP)      |
+-------------------------------------------------------------------------+
                                     |
                         HTTP / REST | WebSockets (Real-time)
                                     v
+-------------------------------------------------------------------------+
|                           BACKEND GATEWAY (Laravel)                     |
|  - Manajemen Sesi & Kebijakan API Key                                   |
|  - Handler Trigger SOAP Audit & Proxy Request                           |
|  - Consumer & Publisher Event Broker                                    |
+-------------------------------------------------------------------------+
         /                           |                            \
        v                            v                             v
+----------------+          +-----------------+          +------------------+
|   Database     |          |  SSO & SOAP     |          |  RabbitMQ Broker |
|   MySQL Local  |          |  Pusat (SOAP)   |          |  (REST Proxy)    |
+----------------+          +-----------------+          +------------------+
```

### A. Interaksi Frontend dengan API Key M2M
- **Penyimpanan Aman (Credentials Vault):** API Key M2M (`KEY-MHS-310`) tidak boleh disimpan secara hardcode di kode frontend. Frontend akan berinteraksi dengan endpoint internal backend Laravel. Backend Laravel yang bertugas menyimpan API Key di `.env` lokal secara aman dan melakukan pertukaran token JWT ke SSO pusat secara berkala.
- **Manajemen API Key Terenkripsi:** Dashboard akan menyediakan menu konfigurasi di mana administrator dapat memperbarui API Key M2M. Data ini disimpan dalam database lokal `102022400285_nilai_dan_kurikulum` pada tabel konfigurasi baru dalam keadaan terenkripsi (`Crypt::encryptString()`).

### B. Memicu SOAP Log Audit secara Manual
- **Manual Trigger Service:** Frontend menyediakan tombol "Picu Ulang Log Audit" (Retry SOAP Log) untuk mendeteksi transaksi inisialisasi yang gagal dikirimkan ke SOAP Audit pusat akibat gangguan jaringan.
- **REST Controller Trigger:** Ketika tombol diklik, frontend mengirimkan request HTTP POST ke endpoint lokal (misal: `/api/v1/dashboard/audit/trigger-retry/{grade_id}`). Backend akan mengambil data grade lokal, meminta token baru dari SSO, lalu memanggil SOAP Audit pusat kembali, memperbarui `receipt_number`, dan mengembalikan status sukses ter-update ke frontend.

### C. Menampilkan Status Broker RabbitMQ (Monitoring Event)
- **Monitoring Antrean Pesan (Queue Broker Status):** Frontend akan mengonsumsi RabbitMQ Management API (atau melalui REST Proxy) secara berkala (polling) atau menggunakan protokol WebSockets (Pusher / Laravel Echo) untuk memantau pergerakan antrean.
- **Tampilan Metrik Visual:** Dashboard menampilkan widget statistik seperti:
  - Jumlah pesan masuk (Message Rate) pada exchange `iae.central.exchange`.
  - Log pengiriman event terakhir dengan rincian routing key `grade.event` dan status acknowledgment (ACK/NACK).
  - Integrasi visual chart/diagram real-time mengenai status keaktifan broker RabbitMQ menggunakan library diagram frontend (seperti Chart.js atau Recharts).

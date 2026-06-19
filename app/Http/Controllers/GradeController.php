<?php

namespace App\Http\Controllers;

use App\Models\Grade;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use App\Services\SsoService;
use App\Services\SoapAuditService;
use App\Services\RabbitMqService;

#[OA\Tag(name: "Grades", description: "Grades & Curriculum API")]
#[OA\SecurityScheme(
    securityScheme: "ApiKeyAuth",
    type: "apiKey",
    in: "header",
    name: "X-IAE-KEY"
)]
class GradeController extends Controller
{
    // =========================================================================
    // ENDPOINT: CURRICULUMS
    // =========================================================================
    #[OA\Get(
        path: "/api/v1/curriculums",
        summary: "Daftar aturan prasyarat kurikulum",
        description: "Menampilkan daftar aturan prasyarat kurikulum program studi untuk mendeteksi keterikatan antar mata kuliah",
        security: [["ApiKeyAuth" => []]],
        tags: ["Grades"]
    )]
    #[OA\Response(
        response: 200,
        description: "Successful response",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "status", type: "string", example: "success"),
                new OA\Property(property: "message", type: "string", example: "Data retrieved successfully"),
                new OA\Property(property: "data", type: "array", items: new OA\Items(
                    properties: [
                        new OA\Property(property: "course_code", type: "string", example: "IF101"),
                        new OA\Property(property: "course_name", type: "string", example: "Dasar Pemrograman"),
                        new OA\Property(property: "prerequisite", type: "string", example: "-")
                    ]
                )),
                new OA\Property(property: "meta", type: "object", properties: [
                    new OA\Property(property: "service_name", type: "string", example: "Grades-and-Curriculum-Service"),
                    new OA\Property(property: "api_version", type: "string", example: "v1")
                ])
            ]
        )
    )]
    public function curriculums()
    {
        $curriculums = [
            ['course_code' => 'IF101', 'course_name' => 'Dasar Pemrograman', 'prerequisite' => '-'],
            ['course_code' => 'IF201', 'course_name' => 'Struktur Data', 'prerequisite' => 'IF101']
        ];

        return response()->json([
            'status' => 'success',
            'message' => 'Data retrieved successfully',
            'data' => $curriculums,
            'meta' => [
                'service_name' => 'Grades-and-Curriculum-Service',
                'api_version' => 'v1'
            ]
        ], 200);
    }

    // =========================================================================
    // ENDPOINT 1: INITIALIZE GRADE (TRANSAKSI KRITIS - ORKESTRASI 3 LAPIS)
    // =========================================================================
    #[OA\Post(
        path: "/api/v1/grades/initialize",
        summary: "Initialize student grade record (Critical Transaction)",
        security: [["ApiKeyAuth" => []]],
        tags: ["Grades"]
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "student_id", type: "string", example: "102022400285"),
                new OA\Property(property: "course_id", type: "string", example: "SI4808"),
                new OA\Property(property: "course_code", type: "string", example: "SI4808")
            ]
        )
    )]
    #[OA\Response(response: 201, description: "Grade initialized and audited successfully")]
    #[OA\Response(response: 401, description: "Invalid API Key")]
    public function initialize(Request $request)
    {
        $request->validate([
            'student_id' => 'required|string',
            'course_id' => 'required_without:course_code|string',
            'course_code' => 'required_without:course_id|string'
        ]);

        // 1. Simpan data awal ke database lokal Docker
        $grade = new Grade();
        $grade->student_id = $request->student_id;
        $grade->course_code = $request->course_code ?? $request->course_id;
        $grade->status = 'BELUM_ADA_NILAI';
        $grade->save();

        $receiptNumber = "PENDING-AUDIT";

        // LAPIS 1 & 2: SSO Login & SOAP Audit
        try {
            $ssoService = new SsoService();
            $soapService = new SoapAuditService();

            // Melakukan login SSO M2M menggunakan API Key kelompok
            $apiKey = env('SSO_PASSWORD', 'KEY-MHS-310');
            $ssoResponse = $ssoService->loginM2M($apiKey);

            if (isset($ssoResponse['token'])) {
                $token = $ssoResponse['token'];

                $logData = [
                    'grade_id' => $grade->id,
                    'student_id' => $grade->student_id,
                    'course_code' => $grade->course_code,
                    'status' => $grade->status
                ];

                // Kirim XML SOAP untuk TEAM-09
                $receiptNumber = $soapService->sendAuditLog(
                    'TEAM-09',
                    'GradeInitialization',
                    $logData,
                    $token
                );

                // Update receipt_number ke DB lokal
                $grade->receipt_number = $receiptNumber;
                $grade->save();
            }
        } catch (\Exception $e) {
            \Log::error("Gagal SOAP Audit: " . $e->getMessage());
        }

        // LAPIS 3: Broadcast Event ke RabbitMQ Dosen
        try {
            if (isset($token) && $receiptNumber) {
                $mqService = new RabbitMqService();
                $mqService->publishEvent('grade.event', [
                    'event' => 'grade.initialized',
                    'team_id' => 'TEAM-09',
                    'student_id' => $grade->student_id,
                    'course_code' => $grade->course_code,
                    'receipt_number' => $receiptNumber,
                    'timestamp' => date('c')
                ], $token);
            }
        } catch (\Exception $e) {
            \Log::error("Gagal RabbitMQ Broadcast: " . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Grade record initialized, audited, and broadcasted!',
            'data' => $grade,
            'iae_audit_receipt' => $receiptNumber,
            'meta' => [
                'service_name' => 'Grades-Curriculum-Service',
                'api_version' => 'v1'
            ]
        ], 201);
    }

    // =========================================================================
    // ENDPOINT 2: GET GRADE BY NIM (student_id)
    // =========================================================================
    #[OA\Get(
        path: "/api/v1/grades/{student_id}",
        summary: "Menampilkan detail riwayat transkrip nilai mahasiswa",
        description: "Menampilkan detail riwayat transkrip nilai mahasiswa untuk pembuktian kelulusan mata kuliah prasyarat",
        security: [["ApiKeyAuth" => []]],
        tags: ["Grades"]
    )]
    #[OA\Parameter(name: "student_id", in: "path", required: true, description: "NIM Mahasiswa", schema: new OA\Schema(type: "string"))]
    #[OA\Response(
        response: 200,
        description: "Grade records found",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "status", type: "string", example: "success"),
                new OA\Property(property: "data", type: "array", items: new OA\Items(
                    properties: [
                        new OA\Property(property: "id", type: "integer", example: 1),
                        new OA\Property(property: "student_id", type: "string", example: "102022400285"),
                        new OA\Property(property: "course_code", type: "string", example: "SI4808"),
                        new OA\Property(property: "grade", type: "string", example: "A"),
                        new OA\Property(property: "status", type: "string", example: "LULUS"),
                        new OA\Property(property: "receipt_number", type: "string", example: "REC-12345")
                    ]
                ))
            ]
        )
    )]
    #[OA\Response(response: 404, description: "Grade records not found")]
    public function show($student_id)
    {
        $grades = Grade::where('student_id', $student_id)->get();

        if ($grades->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Grade records not found for student ID: ' . $student_id
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $grades
        ], 200);
    }
}
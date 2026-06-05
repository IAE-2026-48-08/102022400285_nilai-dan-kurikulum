<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Grade;
use OpenApi\Attributes as OA;

#[OA\Info(
    title: "Grades and Curriculum Service API",
    version: "1.0.0",
    description: "Dokumentasi API untuk Grades-and-Curriculum-Service dengan validasi X-IAE-KEY"
)]
#[OA\Server(
    url: "http://localhost:8080",
    description: "Local Development Server"
)]
class GradeController extends Controller
{
    #[OA\Get(
        path: "/api/v1/curriculums",
        summary: "Daftar aturan prasyarat kurikulum",
        description: "Menampilkan daftar aturan prasyarat kurikulum program studi untuk mendeteksi keterikatan antar mata kuliah"
    )]
    #[OA\Parameter(
        name: "X-IAE-KEY",
        in: "header",
        required: true,
        description: "NIM Mahasiswa untuk otorisasi",
        schema: new OA\Schema(type: "string", default: "102022400285")
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
    #[OA\Response(
        response: 401,
        description: "Unauthorized",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "status", type: "string", example: "error"),
                new OA\Property(property: "message", type: "string", example: "Unauthorized. Invalid X-IAE-KEY."),
                new OA\Property(property: "errors", type: "null")
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

    #[OA\Get(
        path: "/api/v1/grades/{student_id}",
        summary: "Detail riwayat transkrip nilai mahasiswa",
        description: "Menampilkan detail riwayat transkrip nilai mahasiswa untuk pembuktian kelulusan mata kuliah prasyarat"
    )]
    #[OA\Parameter(
        name: "X-IAE-KEY",
        in: "header",
        required: true,
        description: "NIM Mahasiswa untuk otorisasi",
        schema: new OA\Schema(type: "string", default: "102022400285")
    )]
    #[OA\Parameter(
        name: "student_id",
        in: "path",
        required: true,
        description: "ID Mahasiswa",
        schema: new OA\Schema(type: "string")
    )]
    #[OA\Response(
        response: 200,
        description: "Successful response",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "status", type: "string", example: "success"),
                new OA\Property(property: "message", type: "string", example: "Data retrieved successfully"),
                new OA\Property(property: "data", type: "object", properties: [
                    new OA\Property(property: "student_id", type: "string", example: "102022400285"),
                    new OA\Property(property: "gpa", type: "number", format: "float", example: 3.75),
                    new OA\Property(property: "academic_records", type: "array", items: new OA\Items(
                        properties: [
                            new OA\Property(property: "course_code", type: "string", example: "IF101"),
                            new OA\Property(property: "grade", type: "string", example: "A"),
                            new OA\Property(property: "status", type: "string", example: "LULUS")
                        ]
                    ))
                ]),
                new OA\Property(property: "meta", type: "object", properties: [
                    new OA\Property(property: "service_name", type: "string", example: "Grades-and-Curriculum-Service"),
                    new OA\Property(property: "api_version", type: "string", example: "v1")
                ])
            ]
        )
    )]
    #[OA\Response(
        response: 401,
        description: "Unauthorized",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "status", type: "string", example: "error"),
                new OA\Property(property: "message", type: "string", example: "Unauthorized. Invalid X-IAE-KEY."),
                new OA\Property(property: "errors", type: "null")
            ]
        )
    )]
    public function show($student_id)
    {
        // Ambil data riwayat nilai dari database berdasarkan student_id
        $records = Grade::where('student_id', $student_id)->get();

        $academicRecords = [];
        foreach ($records as $record) {
            $academicRecords[] = [
                'course_code' => $record->course_code,
                'grade' => $record->grade,
                'status' => $record->status
            ];
        }

        // Fallback ke data mock jika database kosong untuk student_id ini
        if (empty($academicRecords)) {
            $academicRecords[] = [
                'course_code' => 'IF101',
                'grade' => 'A',
                'status' => 'LULUS'
            ];
        }

        $grades = [
            'student_id' => $student_id,
            'gpa' => 3.75,
            'academic_records' => $academicRecords
        ];

        return response()->json([
            'status' => 'success',
            'message' => 'Data retrieved successfully',
            'data' => $grades,
            'meta' => [
                'service_name' => 'Grades-and-Curriculum-Service',
                'api_version' => 'v1'
            ]
        ], 200);
    }

    #[OA\Post(
        path: "/api/v1/grades/initialize",
        summary: "Membuat baris data nilai baru",
        description: "Membuat baris data (record) nilai baru yang masih kosong di database nilai setelah menerima perintah finalisasi"
    )]
    #[OA\Parameter(
        name: "X-IAE-KEY",
        in: "header",
        required: true,
        description: "NIM Mahasiswa untuk otorisasi",
        schema: new OA\Schema(type: "string", default: "102022400285")
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ["student_id", "course_code"],
            properties: [
                new OA\Property(property: "student_id", type: "string", example: "102022400285"),
                new OA\Property(property: "course_code", type: "string", example: "IF101")
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: "Resource created successfully",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "status", type: "string", example: "success"),
                new OA\Property(property: "message", type: "string", example: "Resource created successfully"),
                new OA\Property(property: "data", type: "object", properties: [
                    new OA\Property(property: "id", type: "integer", example: 1234),
                    new OA\Property(property: "student_id", type: "string", example: "102022400285"),
                    new OA\Property(property: "course_code", type: "string", example: "IF101"),
                    new OA\Property(property: "grade", type: "null"),
                    new OA\Property(property: "status", type: "string", example: "BELUM_ADA_NILAI")
                ]),
                new OA\Property(property: "meta", type: "object", properties: [
                    new OA\Property(property: "service_name", type: "string", example: "Grades-and-Curriculum-Service"),
                    new OA\Property(property: "api_version", type: "string", example: "v1")
                ])
            ]
        )
    )]
    #[OA\Response(
        response: 401,
        description: "Unauthorized",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "status", type: "string", example: "error"),
                new OA\Property(property: "message", type: "string", example: "Unauthorized. Invalid X-IAE-KEY."),
                new OA\Property(property: "errors", type: "null")
            ]
        )
    )]
    public function initialize(Request $request)
    {
        $validated = $request->validate([
            'student_id' => 'required',
            'course_code' => 'required'
        ]);

        // Simpan ke database nyata agar terintegrasi dengan GraphQL
        $grade = Grade::create([
            'student_id' => $request->student_id,
            'course_code' => $request->course_code,
            'grade' => null,
            'status' => 'BELUM_ADA_NILAI'
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Resource created successfully',
            'data' => [
                'id' => $grade->id,
                'student_id' => $grade->student_id,
                'course_code' => $grade->course_code,
                'grade' => $grade->grade,
                'status' => $grade->status
            ],
            'meta' => [
                'service_name' => 'Grades-and-Curriculum-Service',
                'api_version' => 'v1'
            ]
        ], 201);
    }
}

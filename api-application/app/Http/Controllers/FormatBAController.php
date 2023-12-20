<?php

namespace App\Http\Controllers;

use App\Http\Requests\FormatBARequest;
use App\Models\BayiModel;
use App\Models\OrangTuaModel;
use DB;
use App\Models\PenimbanganModel;
use App\Models\PosyanduModel;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class FormatBAController extends Controller
{
    protected $batasBulanStart = [0, 6, 12, 24];
    protected $batasBulanEnd = [5, 11, 23, 59];
    protected $namaBulan = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember'
    ];
    public function get(FormatBARequest $request): JsonResponse
    {
        /**
         * Melakukan validasi data request
         * 
         */
        $data = $request->validated();

        /**
         * Memeriksa apakah data id_bayi ada
         * 
         */
        if (!empty($data['id_bayi'])) {

            /**
             * Mendapatkan informasi bayi
             * 
             */
            $bayi = BayiModel::select(
                'bayi.nama',
                'bayi.tanggal_lahir',
                'bayi.jenis_kelamin',
                'bayi.berat_lahir',
                'format_b.keterangan',
            )
                ->leftJoin('format_b', function ($join) {
                    $join->on('bayi.id', '=', 'format_b.id_bayi');
                })
                ->where('bayi.id', $data['id_bayi'])
                ->first();

            if ($bayi) {

                $tanggal_lahir = Carbon::parse($bayi->tanggal_lahir);

                // Mendapatkan tahun dan bulan bayi berumur 0 - 5 bulan
                $tahun_bulan_bayi = [];

                // Loop dari umur 0 hingga 5 bulan
                for ($i = 0; $i < 60; $i++) {
                    $umur_bayi = $tanggal_lahir->copy()->addMonths($i);
                    $tahun_bulan_bayi[] = [
                        'tahun' => $umur_bayi->year,
                        'bulan' => $umur_bayi->month,
                    ];
                }

                // Konversi hasil ke dalam format yang diinginkan
                $list_waktu = [];
                foreach ($tahun_bulan_bayi as $tahun_bulan) {
                    $list_waktu[] = $tahun_bulan['tahun'] . ' ' . $this->namaBulan[$tahun_bulan['bulan']];
                }

            } else {

                throw new HttpResponseException(response()->json([
                    'errors' => [
                        'message' => 'Data tidak ditemukan'
                    ]
                ])->setStatusCode(400));
            }

            /**
             * Mendapatkan data penimbangan
             * 
             */
            $penimbangan = PenimbanganModel::select(
                'tahun_penimbangan',
                'bulan_penimbangan',
                'berat_badan',
                'ntob',
                'asi_eksklusif',
            )->where('id_bayi', '=', $data['id_bayi'])
                ->orderBy('tahun_penimbangan', 'asc')
                ->orderBy('bulan_penimbangan', 'asc')
                ->get();

            /**
             * Deklarasi list_penimbangan
             * 
             */
            $list_penimbangan = array();

            /**
             * Melakukan perulangan sebanyak data
             * 
             */
            for ($i = 0; $i < count($list_waktu); $i++) {

                $list_penimbangan[$i] = [
                    'judul' => 'Umur ' . $i . ' Bulan - ' . $list_waktu[$i],
                    'berat_badan' => null,
                    'ntob' => null,
                    'asi_eksklusif' => null,
                ];

                /**
                 * Melakukan perulangan sebanyak data yang tersedia
                 * 
                 */
                foreach ($penimbangan as $dataPenimbangan) {
                    if ($dataPenimbangan->tahun_penimbangan . ' ' . $this->namaBulan[$dataPenimbangan->bulan_penimbangan] == $list_waktu[$i]) {
                        $list_penimbangan[$i] = [
                            'judul' => 'Umur ' . $i . ' Bulan - ' . $list_waktu[$i],
                            'berat_badan' => $dataPenimbangan->berat_badan,
                            'ntob' => $dataPenimbangan->ntob,
                            'asi_eksklusif' => $dataPenimbangan->asi_eksklusif,
                        ];
                    }
                }

                /**
                 * Memeriksa apakah data selanjutnya null
                 * jika null, perulangan diberhentikan
                 * 
                 */
                if ($list_penimbangan[$i]['berat_badan'] == null && $list_penimbangan[$i]['asi_eksklusif'] != 'Alpa') {
                    break;
                }

            }

            /**
             * Mengambil standar deviasi dari
             * umur yang dipilih bulan ini
             * 
             */
            $dataWHO = DB::table('standar_deviasi')->select(
                'sangat_kurus',
                'kurus',
                'normal_kurus',
                'baik',
                'normal_gemuk',
                'gemuk',
                'sangat_gemuk'
            )->where('id_berat_untuk_umur', $bayi->jenis_kelamin == 'L' ? 1 : 2)
                ->limit(count($list_penimbangan))
                ->get();

            /**
             * Mengambil keseluruhan data series
             * 
             */
            $series = [
                [
                    "name" => "Terlalu Gemuk",
                    "type" => "line",
                    "data" => $dataWHO->map(function ($item) {
                        return $item->sangat_gemuk;
                    }),
                ],
                [
                    "name" => "Berat Gemuk",
                    "type" => "line",
                    "data" => $dataWHO->map(function ($item) {
                        return $item->gemuk;
                    }),
                ],
                [
                    "name" => "Berat Normal",
                    "type" => "line",
                    "data" => $dataWHO->map(function ($item) {
                        return $item->normal_gemuk;
                    }),
                ],
                [
                    "name" => "Berat Baik",
                    "type" => "line",
                    "data" => $dataWHO->map(function ($item) {
                        return $item->baik;
                    }),
                ],
                [
                    "name" => "Berat Normal",
                    "type" => "line",
                    "data" => $dataWHO->map(function ($item) {
                        return $item->normal_kurus;
                    }),
                ],
                [
                    "name" => "Berat Kurus",
                    "type" => "line",
                    "data" => $dataWHO->map(function ($item) {
                        return $item->kurus;
                    }),
                ],
                [
                    "name" => "Terlalu Kurus, butuh penanganan",
                    "type" => "line",
                    "data" => $dataWHO->map(function ($item) {
                        return $item->sangat_kurus;
                    }),
                ],
                [
                    "name" => "Berat bayi",
                    "type" => "line",
                    "data" =>
                        PenimbanganModel::select('berat_badan')
                            ->where('id_bayi', $data['id_bayi'])
                            ->get()->map(function ($item) {
                                return $item->berat_badan == 0 ? null : $item->berat_badan;
                            })->toArray(),
                ],
            ];
            // PenimbanganModel::select('berat_badan')
            // ->where('id_bayi', $data['id_bayi'])
            // ->pluck('berat_badan')
            // ->toArray(),

            // PenimbanganModel::select('berat_badan')
            //                 ->join('bayi', 'bayi.id', '=', 'penimbangan.id_bayi')
            //                 ->selectRaw('(penimbangan.tahun_penimbangan - YEAR(bayi.tanggal_lahir)) * 12 + penimbangan.bulan_penimbangan - MONTH(bayi.tanggal_lahir) as umur_bulan')
            //                 ->where('id_bayi', $data['id_bayi'])
            //                 ->get()
            //                 ->map(function ($item) {
            //                     return [
            //                         'x' => $item->umur_bulan,
            //                         'y' => $item->berat_badan
            //                     ];
            //                 }),

            /**
             * Mengembalikan response sesuai request
             * 
             */
            return response()->json([
                "bayi" => $bayi,
                "penimbangan" => $list_penimbangan,
                "series" => $series,
            ])->setStatusCode(200);
        }

        /**
         * Memeriksa apakah data request yang
         * dibutuhkan sudah tersedia
         * 
         */
        if (empty($data['tahun']) || empty($data['bulan'])) {
            throw new HttpResponseException(response()->json([
                'errors' => [
                    'message' => 'Data tahun dan bulan tidak boleh kosong'
                ]
            ])->setStatusCode(400));
        }

        /**
         * Memeriksa apakah data tab ada di request
         * 
         */
        if (empty($data['tab'])) {
            throw new HttpResponseException(response()->json([
                'errors' => [
                    'message' => 'Data tab tidak boleh kosong'
                ]
            ])->setStatusCode(400));
        }

        /**
         * Membuat query utama
         * 
         */
        $query = BayiModel::select(
            'bayi.id as id_bayi',
            'bayi.nama as nama_bayi',
            'bayi.jenis_kelamin',
            'penimbangan.berat_badan',
            'penimbangan.ntob',
            'penimbangan.asi_eksklusif'
        )
            ->selectRaw('(' . $data['tahun'] . ' - YEAR(bayi.tanggal_lahir)) * 12 + ' . $data['bulan'] . ' - MONTH(bayi.tanggal_lahir) as umur')
            ->leftJoin('format_b', function ($join) {
                $join->on('bayi.id', '=', 'format_b.id_bayi');
            })
            ->leftJoin('penimbangan', function ($join) use ($data) {
                $join->on('bayi.id', '=', 'penimbangan.id_bayi')
                    ->where('penimbangan.tahun_penimbangan', $data['tahun'])
                    ->where('penimbangan.bulan_penimbangan', $data['bulan'])
                    ->whereRaw('(' . $data['tahun'] . ' - YEAR(bayi.tanggal_lahir)) * 12 + ' . $data['bulan'] . ' - MONTH(bayi.tanggal_lahir) BETWEEN ' . $this->batasBulanStart[$data['tab'] - 1] . ' AND ' . $this->batasBulanEnd[$data['tab'] - 1]);
            })
            ->whereRaw('(' . $data['tahun'] . ' - YEAR(bayi.tanggal_lahir)) * 12 + ' . $data['bulan'] . ' - MONTH(bayi.tanggal_lahir) BETWEEN ' . $this->batasBulanStart[$data['tab'] - 1] . ' AND ' . $this->batasBulanEnd[$data['tab'] - 1])
            ->whereNull('bayi.tanggal_meninggal');


        /**
         * Melakukan filtering atau penyaringan
         * data pada kondisi tertentu
         * 
         */
        if (!empty($data['search'])) {

            /**
             * Memfilter data sesuai request search
             * 
             */
            $query = $query->where('bayi.nama', 'LIKE', '%' . $data['search'] . '%');

        }

        /**
         * Mengambil banyaknya data yang diambil
         * 
         */
        $count = $query->count();

        /**
         * Memeriksa apakah data ingin difilter
         * 
         */
        if (isset($data['start']) && isset($data['length'])) {

            /**
             * Mengambil data gambar dari
             * query yang sudah difilter
             * 
             */
            $query = $query
                ->offset(($data['start'] - 1) * $data['length'])
                ->limit($data['length']);

        }

        /**
         * Mengambil data dari query dan
         * akan dijadikan response
         * 
         */
        $formatBA = $query->get();

        /**
         * Memeriksa apakah id_format_a ada
         * 
         */
        if (!empty($data['id_format_a'])) {

            /**
             * Mengambil query data sesuai id
             * 
             */
            $query = $query->where('format_a.id', $data['id_format_a']);

            /**
             * Mengambil data dari query dan
             * akan dijadikan response
             * 
             */
            $count = $query->count();
            $formatBA = $query->first();

        }

        /**
         * Mengambil data posyandu
         * 
         */
        $posyandu = PosyanduModel::select(
            'nama_posyandu',
            'kota'
        )->first();

        /**
         * Assigment judul format
         * 
         */
        $judulFormat = 'Regrister bayi (' . $this->batasBulanStart[$data['tab'] - 1] . ' - ' . $this->batasBulanEnd[$data['tab'] - 1] . ' bulan) dalam wilayah kerja posyandu Januari - Desember';

        /**
         * Mengembalikan response sesuai request
         * 
         */
        return response()->json([
            'nama_posyandu' => $posyandu->nama_posyandu,
            'kota' => $posyandu->kota,
            "jumlah_data" => $count,
            'judul_format' => $judulFormat,
            "format_ba" => $formatBA
        ])->setStatusCode(200);
    }
    public function post(FormatBARequest $request): JsonResponse
    {
        /**
         * Melakukan validasi data request
         * 
         */
        $data = $request->validated();
        $data['berat_badan'] = floatval($data['berat_badan']);

        /**
         * Mengambil tahun dan bulan dari data judul
         * 
         */
        $dataJudul = explode(' - ', $data['judul']);
        $umurBayi = explode(' ', $dataJudul[0])[1];
        $tahunBulan = explode(' ', $dataJudul[1]);
        $tahunPenimbangan = $tahunBulan[0];
        $bulanPenimbangan = array_search($tahunBulan[1], $this->namaBulan);

        /**
         * Menghabpus data judul
         * 
         */
        unset($data['judul']);

        /**
         * Menambahkan tahun dan bulan ke dalam data
         * 
         */
        $data['tahun_penimbangan'] = intval($tahunPenimbangan);
        $data['bulan_penimbangan'] = intval($bulanPenimbangan);

        /**
         * Mengambil jenis kelamin bayi
         * 
         */
        $jenisKelamin = BayiModel::select('jenis_kelamin')
            ->where('id', $data['id_bayi'])->first()->jenis_kelamin;

        /**
         * Mengambil standar deviasi dari WHO
         * 
         */
        $dataWHO = DB::table('standar_deviasi')->select(
            'sangat_kurus',
            'kurus',
            'normal_kurus',
            'baik',
            'normal_gemuk',
            'gemuk',
            'sangat_gemuk'
        )->where('id_berat_untuk_umur', $jenisKelamin == 'L' ? 1 : 2)
            ->where('umur_bulan', $umurBayi)->first();

        /**
         * Mengambil standar deviasi dari WHO
         * Untuk berat badan umur bulan lalu
         * 
         */
        $dataWHOBulanLalu = DB::table('standar_deviasi')->select(
            'sangat_kurus',
            'kurus',
            'normal_kurus',
            'baik',
            'normal_gemuk',
            'gemuk',
            'sangat_gemuk'
        )->where('id_berat_untuk_umur', $jenisKelamin == 'L' ? 1 : 2)
            ->where('umur_bulan', $umurBayi - 1)->first();

        /**
         * Mengambil data berat badan bulan lalu
         * 
         */
        $beratBadanBulanLalu = PenimbanganModel::select('berat_badan')
            ->where('id_bayi', $data['id_bayi'])
            ->where('tahun_penimbangan', $data['tahun_penimbangan'])
            ->where('bulan_penimbangan', $data['bulan_penimbangan'] - 1)
            ->first();

        /**
         * Memeriksa apakah data kosong
         * 
         */
        if ($data['asi_eksklusif'] == "Alpa" || $data['berat_badan'] == 0) {

            /**
             * Status NTOB dikosongkan jika data kosong
             * 
             */
            $data['ntob'] = "Kosong";

        } else {

            /**
             * Ambil data NTOB sesuai dengan perhitungan
             * 
             */
            $data['ntob'] = $this->getNTOB($umurBayi, $dataWHOBulanLalu, $dataWHO, $beratBadanBulanLalu, $data['berat_badan']);

        }

        /**
         * Menggunakan updateOrCreate untuk menyimpan atau memperbarui data
         * 
         */
        PenimbanganModel::updateOrCreate(
            [
                'id_bayi' => $data['id_bayi'],
                'tahun_penimbangan' => $tahunPenimbangan,
                'bulan_penimbangan' => $bulanPenimbangan,
            ],
            $data
        );

        /**
         * Mengembalikan response setelah
         * melakukan penambahan data
         * 
         */
        return response()->json([
            'success' => [
                'message' => 'Berhasil',
            ]
        ])->setStatusCode(201);
    }
    protected function getNTOB($umurBayi, $dataWHOBulanLalu, $dataWHO, $beratBadanBulanLalu, $beratBadanSekarang)
    {

        if ($umurBayi == 0) {

            return "B (Baru pertama kali menimbang)";

        } else if (empty($beratBadanBulanLalu)) {

            return "O (Tidak menimbang bulan lalu)";

        } else {

            $beratBadanBulanLalu = $beratBadanBulanLalu->berat_badan;

            $pitaBulanLalu = $this->getPitaBeratBadan($beratBadanBulanLalu, $dataWHOBulanLalu);

            $pitaBulanIni = $this->getPitaBeratBadan($beratBadanSekarang, $dataWHO);

            if ($pitaBulanIni == 0) {

                return "BGM (Bayi butuh penanganan khusus)";

            } else if ($beratBadanSekarang > $beratBadanBulanLalu && $pitaBulanIni > $pitaBulanLalu) {

                return "N1 (Naik, Masuk pita diatasnya)";

            } elseif ($pitaBulanIni < $pitaBulanLalu) {

                return "T" . ($beratBadanSekarang > $beratBadanBulanLalu ? "1 (Naik, Namun masuk ke pita bawahnya)" : ($beratBadanSekarang == $beratBadanBulanLalu ? "2 (Tetap, Tidak mengalami pertumbuah)" : "3 (Turun, Tumbuh negatif)"));

            }

        }

        return "N2, Naik, Tetap pada pita yang sama";
    }
    private function getPitaBeratBadan($beratBadan, $dataWHO)
    {
        if ($beratBadan > $dataWHO->sangat_gemuk) {
            return 7;
        } elseif ($beratBadan > $dataWHO->gemuk) {
            return 6;
        } elseif ($beratBadan > $dataWHO->normal_gemuk) {
            return 5;
        } elseif ($beratBadan > $dataWHO->baik) {
            return 4;
        } elseif ($beratBadan > $dataWHO->normal_kurus) {
            return 3;
        } elseif ($beratBadan > $dataWHO->kurus) {
            return 2;
        } elseif ($beratBadan > $dataWHO->sangat_kurus) {
            return 1;
        } else {
            return 0; // Jika berat badan kurang dari sangat kurus
        }
    }
    public function getListTahun(Request $request): JsonResponse
    {
        /**
         * Mendapatkan seluruh list tahun lahir yang
         * bisa dipilih berdasarkan bulan start
         * 
         */
        $listTahunLahir = BayiModel::selectRaw('YEAR(tanggal_lahir) + FLOOR((MONTH(tanggal_lahir) + ' . $this->batasBulanStart[$request->tab] . ') / 12) as tahun_lahir')
            ->orderByDesc('tanggal_lahir')
            ->distinct()
            ->pluck('tahun_lahir')
            ->toArray();

        /**
         * Mendapatkan seluruh list tahun lahir yang
         * bisa dipilih berdasarkan bulan end
         * 
         */
        $listTahunLahir = array_unique(array_merge(
            $listTahunLahir,
            BayiModel::selectRaw('YEAR(tanggal_lahir) + FLOOR((MONTH(tanggal_lahir) + ' . $this->batasBulanEnd[$request->tab] . ') / 12) as tahun_lahir')
                ->orderByDesc('tanggal_lahir')
                ->distinct()
                ->pluck('tahun_lahir')
                ->toArray()
        ));

        /**
         * Mengembalikan response setelah
         * melakukan penambahan data
         * 
         */
        return response()->json(
            $listTahunLahir,
        )->setStatusCode(200);
    }
}

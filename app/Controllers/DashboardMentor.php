<?php

namespace App\Controllers;

use \DateTime;
use App\Models\AbsensiModel;
use App\Models\AnakMagangModel;
use App\Models\LaporanModel;
use App\Models\NilaiModel;
use App\Models\PesertaModel;
use App\Models\RegistrasiModel;
use App\Models\DetailRegisModel;
use App\Models\MentorModel;
use App\Models\UserModel;
use App\Models\KaryawanModel;
use App\Models\DaftarMinatModel;
use App\Libraries\PdfGenerator;
use App\Controllers\BaseController;

use ZipArchive;

class DashboardMentor extends BaseController
{
    protected $session;
    protected $absensiModel;
    protected $anakMagangModel;
    protected $laporanModel;
    protected $nilaiModel;
    protected $pesertaModel;
    protected $registrasiModel;
    protected $detailRegisModel;
    protected $mentorModel;
    protected $daftarMinatModel;
    protected $karyawanModel;

    protected $userModel;
    protected $pdfgenerator;

    public function __construct()
    {


        $this->absensiModel = new AbsensiModel();
        $this->anakMagangModel = new AnakMagangModel();
        $this->laporanModel = new LaporanModel();
        $this->nilaiModel = new NilaiModel();
        $this->pesertaModel = new PesertaModel();
        $this->registrasiModel = new RegistrasiModel();
        $this->detailRegisModel = new DetailRegisModel();
        $this->mentorModel = new MentorModel();
        $this->daftarMinatModel = new DaftarMinatModel();
        $this->userModel = new UserModel();
        $this->karyawanModel = new KaryawanModel();
        $this->pdfgenerator = new PdfGenerator();
        $this->session = session();

        // // Session check
        // if (!session()->get('mentor_logged_in')) {
        //     return redirect()->to('login/mentor');
        // }

        // if (session()->get('level') !== 'mentor') {
        //     session()->setFlashdata('error', 'Anda tidak memiliki akses ke halaman ini.');
        //     session()->destroy();
        //     return redirect()->to('login/mentor');
        // }
    }

    public function encryptor($data)
    {
        $output = false;
        $encrypt_method = "AES-256-CBC";

        $secret_key = 'Tech Area';
        $secret_iv = 'tech@12345678';

        $key = hash('sha256', $secret_key);
        $iv = substr(hash('sha256', $secret_iv), 0, 16);

        $output = openssl_encrypt($data, $encrypt_method, $key, 0, $iv);
        $output = base64_encode($output);

        return $output;

    }

    public function decryptor($data)
    {
        $encrypt_method = "AES-256-CBC";

        $secret_key = 'Tech Area';
        $secret_iv = 'tech@12345678';

        $key = hash('sha256', $secret_key);
        $iv = substr(hash('sha256', $secret_iv), 0, 16);

        $output = openssl_decrypt(base64_decode($data), $encrypt_method, $key, 0, $iv);

        return $output;
    }

    public function index()
    {
        // Cek level pengguna dari session (misalnya 'level' menyimpan informasi jenis pengguna)
        $user_level = $this->session->get('level'); // Pastikan 'level' di-set saat login

        if ($user_level !== 'mentor') {
            return view('no_access');
        }
        $user_nomor = session()->get('nomor');

        // Fetch data
        $total_absen_yang_belum_confirm = $this->absensiModel->getAbsenByMentorCountNotYetConfirm($user_nomor);
        $total_laporan_yang_belum_confirm = $this->laporanModel->getLaporanByMentorCountNotYetConfirm($user_nomor);
        $total_nilai_yang_belum_diisi = $this->nilaiModel->getNilaiByMentorCountNotYetFill($user_nomor);
        $total_anak_bimbingan = $this->pesertaModel->getTotalAnakBimbingan($user_nomor);
        $total_anak_bimbingan_aktif = $this->pesertaModel->getTotalAnakBimbinganAktif($user_nomor);
        $total_anak_bimbingan_tidak_aktif = $this->pesertaModel->getTotalAnakBimbinganTidakAktif($user_nomor);

        // Assign data to the view
        $data = [
            'total_absen_yang_belum_confirm' => $total_absen_yang_belum_confirm,
            'total_laporan_yang_belum_confirm' => $total_laporan_yang_belum_confirm,
            'total_nilai_yang_belum_diisi' => $total_nilai_yang_belum_diisi,
            'total_anak_bimbingan' => $total_anak_bimbingan,
            'total_anak_bimbingan_aktif' => $total_anak_bimbingan_aktif,
            'total_anak_bimbingan_tidak_aktif' => $total_anak_bimbingan_tidak_aktif
        ];

        return view('mentor/header') .
            view('mentor/sidebar') .
            view('mentor/topbar') .
            view('mentor/dashboard', $data) .
            view('mentor/footer');
    }

    public function daftarPeserta()
    {
        // Cek level pengguna dari session (misalnya 'level' menyimpan informasi jenis pengguna)
        $user_level = $this->session->get('level'); // Pastikan 'level' di-set saat login

        if ($user_level !== 'mentor') {
            return view('no_access');
        }
        // Cek level pengguna dari session (misalnya 'level' menyimpan informasi jenis pengguna)
        $user_level = $this->session->get('level'); // Pastikan 'level' di-set saat login

        if ($user_level !== 'mentor') {
            return view('no_access');
        }
        helper('date');
        $user_nomor = session()->get('nomor');
        $data['peserta'] = $this->pesertaModel->getPesertaByMentor($user_nomor);
        foreach ($data['peserta'] as &$register) {
            // Enkripsi ID peserta dan encode dalam base64 untuk digunakan di URL
            $encryptedID = $this->encryptor($register->id_register);
            // Tambahkan ID terenkripsi ke array data
            $register->encrypted_id = $encryptedID;
        }
        $id_register = $this->request->getPost('id_register');
        $detail_regis = $this->detailRegisModel->getDataByRegisterId($id_register);
        // dd($data['peserta']);

        return view('mentor/header') .
            view('mentor/sidebar') .
            view('mentor/topbar') .
            view('mentor/daftar_peserta', $data) .
            view('mentor/footer');
    }

    public function detailDataPeserta($encrypt_id)
    {
        // Cek level pengguna dari session (misalnya 'level' menyimpan informasi jenis pengguna)
        $user_level = $this->session->get('level'); // Pastikan 'level' di-set saat login

        if ($user_level !== 'mentor') {
            return view('no_access');
        }
        helper('date');  // Load helper 'date'
        $registrasiModel = new RegistrasiModel();
        $detailRegisModel = new DetailRegisModel();
        $mentorModel = new MentorModel();

        $id = $this->decryptor($encrypt_id);
        // $instansi = $this->request->getPost('instansi');

        // dd($this->request->getPost());

        // Ambil detail data registrasi
        // $encoded = bin2hex(\CodeIgniter\Encryption\Encryption::createKey(32));
        // $key = hex2bin('key');

        $data['detail'] = $registrasiModel->getDetail($id);
        $data['detail']['encrypt_id'] = $encrypt_id;
        // Ambil detail mentor
        $data['detail_mentor'] = $detailRegisModel->getDetailWithMentor($id);
        // Ambil daftar mentor
        $data['list_mentor'] = [];
        $daftarMinat = $this->daftarMinatModel->findAll(); // Ambil semua data minat
        $data['daftar_minat'] = $daftarMinat;

        // dd($data['detail_mentor']['nipg'] !== null && $data['detail']['status'] == 'Accept');

        // Ambil daftar mentor dan filter berdasarkan jumlah anak bimbingan
        $all_mentors = $mentorModel->getData();  // Ambil semua mentor
        foreach ($all_mentors as $mentor) {
            $nipg = $mentor['nipg'];

            // Hitung jumlah anak bimbingan berdasarkan nipg
            $count_children = $detailRegisModel->countMentorChildren($nipg);

            // Jika jumlah anak bimbingan kurang dari 2, tambahkan ke list_mentor
            if ($count_children < 2) {
                $data['list_mentor'][] = $mentor;
            }
        }

        // Ambil data timeline dari registrasi
        $data['timeline'] = $registrasiModel->getTimeline($id);

        $id_magang = $this->anakMagangModel->getIdMagangByRegister($id);

        $data['anak_magang'] = $this->anakMagangModel->getPesertaByIdMagang($id_magang);
        // dd($data['anak_magang']);
        // dd($data['detail_mentor']);
        // Split timeline berdasarkan tanda koma (atau tanda lainnya sesuai format)
        if (!empty($data['timeline'])) {
            $data['timeline'] = explode(',', $data['timeline']);
        }

        if (!$data['detail']) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Data tidak ditemukan');
        }
        // dd($data);
        return view('mentor/header')
            . view('mentor/sidebar')
            . view('mentor/topbar')
            . view('mentor/detail', $data)
            . view('mentor/footer');
    }

    public function file_lampiran($file_name)
    {
        // Cek level pengguna dari session (misalnya 'level' menyimpan informasi jenis pengguna)
        $user_level = $this->session->get('level'); // Pastikan 'level' di-set saat login

        if ($user_level !== 'mentor') {
            return view('no_access');
        }
        $file_path = FCPATH . 'uploads/' . $file_name;

        if (file_exists($file_path)) {
            return $this->response->download($file_path, null);
        } else {
            throw new \CodeIgniter\Exceptions\PageNotFoundException("File tidak ditemukan");
        }
    }

    public function downloadAll($encrypt_id)
    {
        // Cek level pengguna dari session (misalnya 'level' menyimpan informasi jenis pengguna)
        $user_level = $this->session->get('level');

        if ($user_level !== 'mentor') {
            return view('no_access');
        }

        $id = $this->decryptor($encrypt_id);

        $user_data = $this->registrasiModel->getUserFiles($id);
        $user_data_diri = $this->registrasiModel->getUserDataDiri($id);

        if (!$user_data || !$user_data_diri) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Data tidak ditemukan');
        }

        $upload_path = FCPATH . 'uploads/';
        $zip = new ZipArchive();
        // Gunakan direktori sementara yang disediakan sistem
        $zip_file_path = sys_get_temp_dir() . '/lampiran_magang_' . uniqid() . '.zip';

        if ($zip->open($zip_file_path, ZipArchive::CREATE) !== true) {
            throw new \RuntimeException('Tidak dapat membuat file ZIP');
        }

        foreach ($user_data as $file) {
            $file_path = $upload_path . $file;

            if (file_exists($file_path)) {
                $zip->addFile($file_path, basename($file_path));
            } else {
                log_message('error', "File tidak ditemukan: $file_path");
            }
        }

        $nama = $user_data_diri['nama'];
        $nim = $user_data_diri['nik'];
        $instansi = $user_data_diri['instansi'];
        $date = date('Y-m-d');

        $zip_file_name = 'lampiran_magang_' . $instansi . '_' . $nama . '_' . $nim . '_' . $date . '.zip';

        if ($zip->close() === false) {
            throw new \RuntimeException('Tidak dapat menutup file ZIP');
        }

        return $this->response->download($zip_file_path, null)->setFileName($zip_file_name);
    }

    public function cari_co_mentor($encrypt_id)
    {
        // Cek level pengguna dari session (misalnya 'level' menyimpan informasi jenis pengguna)
        $user_level = $this->session->get('level'); // Pastikan 'level' di-set saat login

        if ($user_level !== 'mentor') {
            return view('no_access');
        }
        helper('date');  // Load helper 'date'
        $registrasiModel = new RegistrasiModel();
        $detailRegisModel = new DetailRegisModel();
        $mentorModel = new MentorModel();
        $id = $this->decryptor($encrypt_id);
        // $instansi = $this->request->getPost('instansi');

        // dd($this->request->getPost());

        // Ambil detail data registrasi
        $data['detail'] = $registrasiModel->getDetail($id);
        $data['detail']['encrypt_id'] = $encrypt_id;
        // Ambil detail mentor
        $data['detail_mentor'] = $detailRegisModel->getDetailWithMentor($id);
        // Ambil daftar mentor
        $data['list_mentor'] = [];

        // dd($data['detail_mentor']['nipg'] !== null && $data['detail']['status'] == 'Accept');

        // Ambil daftar mentor dan filter berdasarkan jumlah anak bimbingan
        $all_mentors = $mentorModel->getData();  // Ambil semua mentor
        foreach ($all_mentors as $mentor) {
            $nipg = $mentor['nipg'];

            // Hitung jumlah anak bimbingan berdasarkan nipg
            $count_children = $detailRegisModel->countMentorChildren($nipg);

            // Jika jumlah anak bimbingan kurang dari 2, tambahkan ke list_mentor
            if ($count_children < 2) {
                $data['list_mentor'][] = $mentor;
            }
        }

        // Ambil data timeline dari registrasi
        $data['timeline'] = $registrasiModel->getTimeline($id);

        $id_magang = $this->anakMagangModel->getIdMagangByRegister($id);

        $data['anak_magang'] = $this->anakMagangModel->getPesertaByIdMagangOne($id_magang);
        $coMentors = $this->karyawanModel->getData();  // Misalnya berdasarkan nipg mentor
        $data['co_mentors'] = $coMentors;
        if ($data['anak_magang'] !== null) {
            if ($data['anak_magang']['nipg_co_mentor'] !== null) {
                $nipg = $data['anak_magang']['nipg_co_mentor'];
                $data['co_mentor'] = $this->karyawanModel->getDataByNipg($nipg);

            }

        }

        // dd($nipg);

        // Split timeline berdasarkan tanda koma (atau tanda lainnya sesuai format)
        if (!empty($data['timeline'])) {
            $data['timeline'] = explode(',', $data['timeline']);
        }

        if (!$data['detail']) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Data tidak ditemukan');
        }

        return view('mentor/header')
            . view('mentor/sidebar')
            . view('mentor/topbar')
            . view('mentor/cari_mentor', $data)
            . view('mentor/footer');
    }

    public function assign_co_mentor($encrypt_id)
    {
        // Cek level pengguna dari session (misalnya 'level' menyimpan informasi jenis pengguna)
        $user_level = $this->session->get('level'); // Pastikan 'level' di-set saat login

        if ($user_level !== 'mentor') {
            return view('no_access');
        }
        // Ambil data dari form
        $id_register = $this->decryptor($encrypt_id);
        $nipg = $this->request->getPost('nipg');  // Mentor yang dipilih

        // Ambil data peserta berdasarkan id_register
        $peserta = $this->registrasiModel->getPesertaById($id_register);

        // Pastikan data peserta ada
        if (empty($peserta)) {
            return redirect()->back()->with('error', 'Peserta tidak ditemukan');
        }

        // Pastikan mentor yang dipilih ada (nipg tidak kosong)
        if (empty($nipg)) {
            return redirect()->back()->with('error', 'Silakan pilih mentor');
        }

        // Update nipg_co_mentor di tabel anak_magang berdasarkan id_register
        $anakMagangModel = new AnakMagangModel();  // Model untuk tabel anak_magang

        $dataToUpdate = [
            'nipg_co_mentor' => $nipg  // Menyimpan nipg co-mentor
        ];

        $this->registrasiModel->updateTimelineAcc($id_register, 'Upload Surat');


        // Lakukan update pada tabel anak_magang untuk peserta yang sesuai id_register
        $updateResult = $anakMagangModel->updateByRegisterId($id_register, $dataToUpdate);

        if ($updateResult) {
            // Redirect kembali ke halaman detail dengan pesan sukses
            return redirect()->to('/mentor/dashboard/detail_data_peserta/' . $encrypt_id)->with('success', 'Co-mentor berhasil dipilih');
        } else {
            // Jika update gagal
            return redirect()->back()->with('error', 'Gagal memperbarui co-mentor');
        }
    }
    public function approve_peserta()
    {
        // Cek level pengguna dari session (misalnya 'level' menyimpan informasi jenis pengguna)
        $user_level = $this->session->get('level'); // Pastikan 'level' di-set saat login

        if ($user_level !== 'mentor') {
            return view('no_access');
        }
        helper('date');

        if ($this->request->isAJAX()) {
            try {
                $input = $this->request->getJSON();

                if (!isset($input->id_magang, $input->id_register)) {
                    return $this->response->setJSON(['success' => false, 'message' => 'Data tidak valid']);
                }
                $idMagang = $input->id_magang;
                $idRegister = $input->id_register;

                // Load model
                $anakMagangModel = new AnakMagangModel();
                $detailRegisModel = new DetailRegisModel();
                $registrasiModel = new RegistrasiModel();
                $userModel = new UserModel();
                $nilaiModel = new NilaiModel();
                $absenModel = new AbsensiModel();

                // Get data registrasi
                $registrasi = $registrasiModel->find($idRegister);
                if (!$registrasi) {
                    return $this->response->setJSON(['success' => false, 'message' => 'Data registrasi tidak ditemukan']);
                }

                // Update timeline status di tabel registrasi
                // $registrasiModel->updateTimelineAccMentor($idRegister, 'Review Surat Perjanjian');

                // Update timeline status di tabel registrasi
                // $registrasiModel->updateStatusAccMentor($idRegister, 'Accept');

                // Update status di tabel anak_magang
                $anakMagangModel->update($idMagang, ['status' => 'Waiting']);

                // Update approved di tabel detailregis
                $detailRegisModel->where('id_register', $idRegister)->set(['approved' => 'Y'])->update();

                // Insert data ke tabel users
                // $username = strtolower($registrasi['tipe']) . $idRegister;
                // $password = bin2hex(random_bytes(4));
                // $userModel->insert([
                //     'nomor' => $registrasi['nomor'],
                //     'username' => $username,
                //     'password' => password_hash($password, PASSWORD_BCRYPT),
                //     'level' => 'user',
                //     'aktif' => 'Y',
                //     'id_register' => $idRegister
                // ]);

                // Insert default nilai for the new participant
                $nilaiModel->insert([
                    'id_magang' => $idMagang,
                    // Nilai lainnya
                ]);

                // Generate absen data for the participant
                $tanggalMulai = new DateTime($registrasi['tanggal1']);
                $tanggalSelesai = new DateTime($registrasi['tanggal2']);
                $tanggalSekarang = clone $tanggalMulai;

                $absenData = [];
                while ($tanggalSekarang <= $tanggalSelesai) {
                    $absenData[] = [
                        'id_magang' => $idMagang,
                        'tgl' => $tanggalSekarang->format('Y-m-d'),
                    ];
                    $tanggalSekarang->modify('+1 day');
                }

                if (!$absenModel->insertBatch($absenData)) {
                    log_message('error', 'Insert batch error: ' . json_encode($absenModel->errors()));
                    return $this->response->setJSON(['success' => false, 'message' => 'Gagal membuat data absen']);
                }

                //Get mentor data
                $mentor = $anakMagangModel->select('mentor.nama, mentor.nipg, mentor.email, mentor.division')
                    ->join('mentor', 'mentor.id_mentor = anak_magang.id_mentor')
                    ->where('anak_magang.id_magang', $idMagang)
                    ->first();

                if (!$mentor) {
                    return $this->response->setJSON(['success' => false, 'message' => 'Data mentor tidak ditemukan']);
                }

                // Send email to peserta
                // if (!$this->sendEmailToPeserta($registrasi, 'Accept', $mentor, $username, $password)) {
                //     return $this->response->setJSON(['success' => false, 'message' => 'Gagal mengirim email ke peserta']);
                // }

                return $this->response->setJSON(['success' => true, 'message' => 'Peserta berhasil diapprove']);
            } catch (\Exception $e) {
                log_message('error', 'Error saat memproses approve peserta: ' . $e->getMessage());
                return $this->response->setJSON(['success' => false, 'message' => 'Terjadi kesalahan pada server']);
            }
        }
        return $this->response->setJSON(['success' => false, 'message' => 'Invalid request']);
    }
    private function sendEmailToPeserta($peserta, $status, $mentor = null, $username = null, $password = null)
    {
        // Cek level pengguna dari session (misalnya 'level' menyimpan informasi jenis pengguna)
        $user_level = $this->session->get('level'); // Pastikan 'level' di-set saat login

        if ($user_level !== 'mentor') {
            return view('no_access');
        }
        $email = \Config\Services::email();

        if (empty($peserta['email'])) {
            log_message('error', 'Email peserta tidak tersedia.');
            return false;
        }

        $email->setFrom('ormasbbctestt@gmail.com', 'PGN GAS Admin Internship Program');
        $email->setTo($peserta['email']);

        if ($status === 'Accept' && $mentor && $username && $password) {
            $email->setSubject('Selamat! Pendaftaran Anda Telah Diterima');
            $email->setMessage("
        <html>
        <head>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    line-height: 1.6;
                }
                table {
                    border-collapse: collapse;
                    width: 100%;
                    margin-bottom: 20px;
                }
                th, td {
                    border: 1px solid #ddd;
                    padding: 8px;
                    text-align: left;
                }
                th {
                    background-color: #f2f2f2;
                }
                .button {
                    background-color: #4CAF50;
                    color: white;
                    padding: 10px 20px;
                    text-decoration: none;
                    font-size: 16px;
                    border-radius: 5px;
                    display: inline-block;
                    margin-top: 20px;
                }
            </style>
        </head>
        <body>
            <p>Kepada Yth. {$peserta['nama']},</p>
            <p>Dengan hormat,</p>
            <p>Kami dengan senang hati menginformasikan bahwa pendaftaran Anda dalam program ini telah diterima.</p>
            
            <h4>Informasi Akun Anda:</h4>
            <p>Username : {$username}</p>
            <p>Password : {$password}</p>
            <br>
            <h4>Informasi Mentor Anda:</h4>
            <p>Nama : {$mentor['nama']}</p>
            <p>NIPG : {$mentor['nipg']}</p>
            <p>Email : {$mentor['email']}</p>
            <p>Satuan Kerja : {$mentor['division']}</p>
            <p>Program : {$peserta['tipe']}</p>
            <br>
            <p>Silakan login ke sistem kami menggunakan username dan password di atas untuk informasi lebih lanjut dan memulai program ini. Jika Anda memiliki pertanyaan, jangan ragu untuk menghubungi kami.</p>
            <p>Terima kasih atas partisipasi Anda.</p>
            
            <p>Hormat kami,<br>Admin Program</p>
            <p><a href='" . base_url('login') . "'>Login</a></p>
        </body>
        </html>
        ");
        } elseif ($status === 'reject') {
            $email->setSubject('Hasil Pendaftaran Program');
            $email->setMessage("
        <html>
        <head>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    line-height: 1.6;
                }
            </style>
        </head>
        <body>
            <p>Kepada Yth. {$peserta['nama']},</p>
            <p>Dengan hormat,</p>
            <p>Kami mengucapkan terima kasih atas minat dan partisipasi Anda dalam program ini. Namun, dengan berat hati kami sampaikan bahwa pendaftaran Anda belum dapat diterima.</p>
            <p>Kami mendorong Anda untuk tetap semangat dan terus meningkatkan kemampuan Anda. Jika ada pertanyaan lebih lanjut, silakan hubungi tim kami.</p>
            <p>Hormat kami,<br>Admin Program</p>
        </body>
        </html>
        ");
        }

        // Proses pengiriman email
        if (!$email->send()) {
            log_message('error', 'Email gagal dikirim ke ' . $peserta['email']);
            log_message('error', 'Debugger Email: ' . $email->printDebugger(['headers', 'subject', 'body']));
            return false;
        }

        log_message('info', 'Email berhasil dikirim ke ' . $peserta['email']);
        return true;
    }
    public function absensiBimbingan()
    {
        // Cek level pengguna dari session (misalnya 'level' menyimpan informasi jenis pengguna)
        $user_level = $this->session->get('level'); // Pastikan 'level' di-set saat login

        if ($user_level !== 'mentor') {
            return view('no_access');
        }
        helper('date');
        $user_nomor = session()->get('nomor');
        $data['absen'] = $this->absensiModel->getAbsenByMentor($user_nomor);
        // dd($data['absen']);

        return view('mentor/header') .
            view('mentor/sidebar') .
            view('mentor/topbar') .
            view('mentor/absensi_bimbingan', $data) .
            view('mentor/footer');
    }

    public function updateStatusAbsensi()
    {
        // Cek level pengguna dari session (misalnya 'level' menyimpan informasi jenis pengguna)
        $user_level = $this->session->get('level'); // Pastikan 'level' di-set saat login

        if ($user_level !== 'mentor') {
            return view('no_access');
        }
        if ($this->request->isAJAX()) {
            $data = $this->request->getJSON();

            // Debugging: lihat data yang diterima
            log_message('debug', 'Received Data: ' . print_r($data, true));

            $id_magang = $data->id_magang ?? null;
            $status = $data->status ?? null;
            $tgl = $data->tgl ?? null;
            if ($status == 'Y') {
                $statuss = "Hadir";
            } else {
                $statuss = "Tidak Hadir";
            }

            if ($id_magang && in_array($status, ['Y', 'N'])) {
                // Update status
                $this->absensiModel->updateStatusAbsensi($id_magang, $tgl, $status, $statuss);

                return $this->response->setJSON(['success' => true]);
            } else {
                return $this->response->setJSON(['success' => false, 'message' => 'Data tidak valid.']);
            }
        } else {
            return redirect()->to('/404');
        }
    }
    public function rekapAbsensiBimbingan()
    {
        // Cek level pengguna dari session (misalnya 'level' menyimpan informasi jenis pengguna)
        $user_level = $this->session->get('level'); // Pastikan 'level' di-set saat login

        if ($user_level !== 'mentor') {
            return view('no_access');
        }
        helper('date');
        $user_nomor = session()->get('nomor');
        $data['peserta'] = $this->pesertaModel->getPesertaByMentor($user_nomor);
        // dd($data['peserta']);
        foreach ($data['peserta'] as &$peserta) {
            // Enkripsi ID peserta dan encode dalam base64 untuk digunakan di URL
            $encryptedID = $this->encryptor($peserta->id_magang);
            // Tambahkan ID terenkripsi ke array data
            $peserta->encrypted_id = $encryptedID;
        }

        return view('mentor/header') .
            view('mentor/sidebar') .
            view('mentor/topbar') .
            view('mentor/rekap_absensi_bimbingan', $data) .
            view('mentor/footer');
    }

    public function detailRekapAbsensiBimbingan($encrypt_id)
    {
        // Cek level pengguna dari session (misalnya 'level' menyimpan informasi jenis pengguna)
        $user_level = $this->session->get('level'); // Pastikan 'level' di-set saat login

        if ($user_level !== 'mentor') {
            return view('no_access');
        }
        helper('date');
        $id_magang = $this->decryptor($encrypt_id);
        $user_nomor = session()->get('nomor');

        $start_date = $this->request->getGet('start_date');
        $end_date = $this->request->getGet('end_date');
        $filter_type = $this->request->getGet('filter_type');

        if ($filter_type == '7_days') {
            $start_date = date('Y-m-d', strtotime('-7 days'));
            $end_date = date('Y-m-d');
        } elseif ($filter_type == '1_month') {
            $start_date = date('Y-m-d', strtotime('-1 month'));
            $end_date = date('Y-m-d');
        } elseif ($filter_type == '3_months') {
            $start_date = date('Y-m-d', strtotime('-3 months'));
            $end_date = date('Y-m-d');
        }

        $data = [
            'peserta' => $this->pesertaModel->getDetailAbsenPesertaByMentor($user_nomor, $id_magang, $start_date, $end_date),
            'id_magang' => $id_magang,
            'encrypt_id' => $encrypt_id
        ];

        return view('mentor/header') .
            view('mentor/sidebar') .
            view('mentor/topbar') .
            view('mentor/detail_rekap_absensi_bimbingan', $data) .
            view('mentor/footer');
    }


    public function cetakDetailRekapAbsensiBimbingan($encrypt_id)
    {
        // Cek level pengguna dari session (misalnya 'level' menyimpan informasi jenis pengguna)
        $user_level = $this->session->get('level'); // Pastikan 'level' di-set saat login

        if ($user_level !== 'mentor') {
            return view('no_access');
        }
        helper('date');
        $id_magang = $this->decryptor($encrypt_id);
        $user_nomor = session()->get('nomor');

        $start_date = $this->request->getGet('start_date');
        $end_date = $this->request->getGet('end_date');
        $filter_type = $this->request->getGet('filter_type');

        if ($filter_type == '7_days') {
            $start_date = date('Y-m-d', strtotime('-7 days'));
            $end_date = date('Y-m-d');
        } elseif ($filter_type == '1_month') {
            $start_date = date('Y-m-d', strtotime('-1 month'));
            $end_date = date('Y-m-d');
        } elseif ($filter_type == '3_months') {
            $start_date = date('Y-m-d', strtotime('-3 months'));
            $end_date = date('Y-m-d');
        }

        $data = [
            'peserta' => $this->pesertaModel->getDetailAbsenPesertaByMentor($user_nomor, $id_magang, $start_date, $end_date),
            'id_magang' => $id_magang,
            'encrypt_id' => $encrypt_id
        ];

        $this->pdfgenerator->generate(
            view('mentor/cetak_detail_rekap_absensi_bimbingan', $data),
            "Detail Rekap Absensi",
            'A4',
            'landscape'
        );
    }

    public function laporanBimbingan()
    {
        // Cek level pengguna dari session (misalnya 'level' menyimpan informasi jenis pengguna)
        $user_level = $this->session->get('level'); // Pastikan 'level' di-set saat login

        if ($user_level !== 'mentor') {
            return view('no_access');
        }
        $user_nomor = session()->get('nomor');
        $data['laporan'] = $this->anakMagangModel->getLaporanAkhirByMentor($user_nomor);
        // dd($data);
        return view('mentor/header') .
            view('mentor/sidebar') .
            view('mentor/topbar') .
            view('mentor/laporan_bimbingan', $data) .
            view('mentor/footer');
    }

    public function updateStatusLaporanAkhir()
    {
        // Cek level pengguna dari session (misalnya 'level' menyimpan informasi jenis pengguna)
        $user_level = $this->session->get('level'); // Pastikan 'level' di-set saat login

        if ($user_level !== 'mentor') {
            return view('no_access');
        }
        // Ambil data yang dikirim oleh frontend
        $data = $this->request->getJSON();

        if (isset($data->id_magang) && isset($data->status)) {
            $idMagang = $data->id_magang;
            $status = $data->status;

            // Update status laporan akhir
            $updateStatus = $this->anakMagangModel->updateStatusLaporanAkhir($idMagang, $status);

            if ($updateStatus) {
                return $this->response->setJSON(['success' => true]);
            } else {
                return $this->response->setJSON(['success' => false]);
            }
        }

        return $this->response->setJSON(['success' => false, 'message' => 'Data tidak valid']);
    }


    public function file($file_name)
    {
        // Cek level pengguna dari session (misalnya 'level' menyimpan informasi jenis pengguna)
        $user_level = $this->session->get('level'); // Pastikan 'level' di-set saat login

        if ($user_level !== 'mentor') {
            return view('no_access');
        }
        $file_path = FCPATH . 'uploads/laporan/' . $file_name; // Gunakan WRITEPATH untuk folder writable

        // Debugging: Log the file path
        log_message('debug', 'Looking for file: ' . $file_path);

        if (file_exists($file_path)) {
            return $this->response->download($file_path, null);
        } else {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('File tidak ditemukan: ' . $file_path);
        }
    }


    public function riwayatLaporanBimbingan()
    {
        // Cek level pengguna dari session (misalnya 'level' menyimpan informasi jenis pengguna)
        $user_level = $this->session->get('level'); // Pastikan 'level' di-set saat login

        if ($user_level !== 'mentor') {
            return view('no_access');
        }
        $user_nomor = session()->get('nomor');
        $data['laporan'] = $this->anakMagangModel->getLaporanAkhirByMentor($user_nomor);


        return view('mentor/header') .
            view('mentor/sidebar') .
            view('mentor/topbar') .
            view('mentor/riwayat_laporan_bimbingan', $data) .
            view('mentor/footer');
    }

    public function nilaiBimbingan()
    {
        // Cek level pengguna dari session (misalnya 'level' menyimpan informasi jenis pengguna)
        $user_level = $this->session->get('level'); // Pastikan 'level' di-set saat login

        if ($user_level !== 'mentor') {
            return view('no_access');
        }
        helper('date');
        $user_nomor = session()->get('nomor');
        $data['nilai'] = $this->nilaiModel->getNilaiByMentor($user_nomor);
        return view('mentor/header') .
            view('mentor/sidebar') .
            view('mentor/topbar') .
            view('mentor/nilai_bimbingan', $data) .
            view('mentor/footer');
    }

    public function simpan_nilai()
    {
        $user_level = $this->session->get('level');

        if ($user_level !== 'mentor') {
            return view('no_access');
        }

        $id_magang = $this->request->getPost('id_magang');
        $id_register = $this->request->getPost('id_register');
        $tanggung_jawab = $this->request->getPost('tanggung_jawab');
        $kehadiran = $this->request->getPost('kehadiran');
        $kemampuan_kerja = $this->request->getPost('kemampuan_kerja');
        $integritas = $this->request->getPost('integritas');
        $perilaku = (int)$this->request->getPost('perilaku');
        
        // if($perilaku == 'Sangat Baik'){
        //     $perilaku = 100;
        // } else if($perilaku == 'Baik'){
        //     $perilaku = 90;
        // } else if($perilaku == 'Cukup Baik'){
        //     $perilaku = 80;
        // } else{
        //     $perilaku = 70;
        // }
        $total = $tanggung_jawab + $kehadiran + $kemampuan_kerja + $integritas + $perilaku;
        $rata = $total / 5;
        if($rata < 60){
            $predikat = "Tidak Memuaskan";
        } else if ($rata < 80){
            $predikat = "Memuaskan";
        } else{
            $predikat = "Sangat Memuaskan";
        }

        $data = [
            'tanggung_jawab' => $this->request->getPost('tanggung_jawab'),
            'kehadiran' => $this->request->getPost('kehadiran'),
            'kemampuan_kerja' => $this->request->getPost('kemampuan_kerja'),
            'integritas' => $this->request->getPost('integritas'),
            'perilaku' => $this->request->getPost('perilaku'),
            'rata' => $rata,
            'predikat'=> $predikat,
            'tgl_input' => date('Y-m-d'),
        ];

        // Log data nilai
        log_message('debug', 'Data yang diterima untuk simpan_nilai: ' . json_encode($data));

        $model = new NilaiModel();

        if ($model->updateNilai($data, $id_magang)) {
            // Ambil nilai no_sertif terakhir
            $registrasiModel = new RegistrasiModel();
            $last_no_sertif = $registrasiModel->getLastNoSertif();

            // Pastikan no_sertif adalah angka
            log_message('debug', "No_sertif terakhir: " . $last_no_sertif);

            // Jika no_sertif terakhir ditemukan, tambahkan 1
            $new_no_sertif = $last_no_sertif + 1;

            // Log perubahan no_sertif
            log_message('debug', "No_sertif baru: " . $new_no_sertif);

            // Update no_sertif pada tabel registrasi
            $updateData = [
                'no_sertif' => $new_no_sertif
            ];

            if ($registrasiModel->updateNoSertif($id_register, $updateData)) {
                log_message('debug', "No Sertif berhasil diperbarui menjadi $new_no_sertif untuk id_magang: $id_register");
            } else {
                log_message('error', "Gagal memperbarui no_sertif untuk id_magang: $id_register");
            }

            return $this->response->setJSON(['success' => true, 'message' => 'Nilai berhasil diperbarui, dan no_sertif diperbarui']);
        } else {
            log_message('error', "Gagal memperbarui nilai untuk id_magang: $id_register");
            return $this->response->setJSON(['success' => false, 'message' => 'Gagal memperbarui nilai']);
        }
    }


    public function riwayatNilaiBimbingan()
    {
        // Cek level pengguna dari session (misalnya 'level' menyimpan informasi jenis pengguna)
        $user_level = $this->session->get('level'); // Pastikan 'level' di-set saat login

        if ($user_level !== 'mentor') {
            return view('no_access');
        }
        helper('date');
        $user_nomor = session()->get('nomor');

        // Memuat model
        $model = new NilaiModel();

        // Mengambil data nilai
        $data['nilai'] = $model->getNilaiByMentor($user_nomor);
        foreach ($data['nilai'] as &$nilai) {
            // Enkripsi ID peserta dan encode dalam base64 untuk digunakan di URL
            $encryptedID = $this->encryptor($nilai->id_magang);
            // Tambahkan ID terenkripsi ke array data
            $nilai->encrypted_id = $encryptedID;
        }

        // dd($data['nilai']);

        foreach ($data['nilai'] as $item) {
            $item->status = $item->rata > 75 ? 'Lulus' : 'Tidak Lulus';
        }

        // dd($data['nilai']);

        return view('mentor/header') .
            view('mentor/sidebar') .
            view('mentor/topbar') .
            view('mentor/riwayat_nilai_bimbingan', $data) .
            view('mentor/footer');
    }

    public function detailRiwayatNilaiBimbingan($encrypt_id)
    {
        // Cek level pengguna dari session (misalnya 'level' menyimpan informasi jenis pengguna)
        $user_level = $this->session->get('level'); // Pastikan 'level' di-set saat login

        if ($user_level !== 'mentor') {
            return view('no_access');
        }
        helper('date');

        $user_nomor = session()->get('nomor');

        // Memuat model
        $model = new NilaiModel();

        // Mengambil nilai berdasarkan mentor
        $id_magang = $this->decryptor($encrypt_id);
        $data['nilai_akhir'] = $model->getNilaiByIdMagangFull($id_magang);
        $data['nilai_akhir_pure'] = $model->getNilaiByIdMagangPure($id_magang);
        // dd($data['nilai_akhir']);
        $data['id_magang'] = $id_magang;
        // dd($data['nilai_akhir_pure']);
        $total = 0;
        $total += $data['nilai_akhir_pure']['kehadiran'];
        $total += $data['nilai_akhir_pure']['tanggung_jawab'];
        $total += $data['nilai_akhir_pure']['kemampuan_kerja'];
        $total += $data['nilai_akhir_pure']['integritas'];
        $total += $data['nilai_akhir_pure']['perilaku'];


        // switch ($data['nilai_akhir_pure']['perilaku']) {
        //     case 'Sangat Baik':
        //         $total += 100;
        //         break;
        //     case 'Baik':
        //         $total += 75;
        //         break;
        //     case 'Cukup Baik':
        //         $total += 50;
        //         break;
        //     case 'Tidak Baik':
        //         $total += 0;
        //         break;
        // }

        $total = $total / 5;

        $data['status'] = $total > 75 ? 'Lulus' : 'Tidak Lulus';
        $data['encrypt_id'] = $encrypt_id;


        // Menampilkan view
        return view('mentor/header') .
            view('mentor/sidebar') .
            view('mentor/topbar') .
            view('mentor/detail_riwayat_nilai_bimbingan', $data) .
            view('mentor/footer');
    }

    private function hitungTotalNilai($item)
    {
        // Cek level pengguna dari session (misalnya 'level' menyimpan informasi jenis pengguna)
        $user_level = $this->session->get('level'); // Pastikan 'level' di-set saat login

        if ($user_level !== 'mentor') {
            return view('no_access');
        }
        $total = 0;

        $total += $item->ketepatan_waktu;
        $total += $item->sikap_kerja;
        $total += $item->tanggung_jawab;
        $total += $item->kehadiran;
        $total += $item->kemampuan_kerja;
        $total += $item->keterampilan_kerja;
        $total += $item->kualitas_hasil;
        $total += $item->kemampuan_komunikasi;
        $total += $item->kerjasama;
        $total += $item->kerajinan;
        $total += $item->percaya_diri;
        $total += $item->mematuhi_aturan;
        $total += $item->penampilan;

        switch ($item->perilaku) {
            case 'Sangat Baik':
                $total += 100;
                break;
            case 'Baik':
                $total += 75;
                break;
            case 'Cukup Baik':
                $total += 50;
                break;
            case 'Tidak Baik':
                $total += 0;
                break;
        }

        return $total / 14;
    }

    public function cetakDetailRiwayatNilaiBimbingan()
    {
        // Cek level pengguna dari session (misalnya 'level' menyimpan informasi jenis pengguna)
        $user_level = $this->session->get('level'); // Pastikan 'level' di-set saat login

        if ($user_level !== 'mentor') {
            return view('no_access');
        }
        helper('date');

        $user_nomor = session()->get('nomor');
        $id = $this->request->getUri()->getSegment(4);
        $id_magang = $this->decryptor($id);

        // Memuat model
        // Mengambil nilai berdasarkan mentor
        $nilai_akhir = $this->nilaiModel->getNilaiByIdMagangFull($id_magang);
        $nilai_akhir_pure = $this->nilaiModel->getNilaiByIdMagangPure($id_magang);
        $data['nilai_akhir'] = $this->nilaiModel->getNilaiByIdMagangFull($id_magang);
        $data['nilai_akhir_pure'] = $this->nilaiModel->getNilaiByIdMagangPure($id_magang);
        // dd($data['nilai_akhir_pure']);

        // dd($data['nilai_akhir']);
        $data['id_magang'] = $id_magang;
        // dd($data['nilai_akhir_pure']);
        $total = 0;
        $total += $data['nilai_akhir_pure']['kehadiran'];
        $total += $data['nilai_akhir_pure']['tanggung_jawab'];
        $total += $data['nilai_akhir_pure']['kemampuan_kerja'];
        $total += $data['nilai_akhir_pure']['integritas'];        
        $total += (int)$data['nilai_akhir_pure']['perilaku'];

        // dd($data['nilai_akhir_pure']['perilaku']);

        // switch ($data['nilai_akhir_pure']['perilaku']) {
        //     case 'Sangat Baik':
        //         $total += 100;
        //         break;
        //     case 'Baik':
        //         $total += 90;
        //         break;
        //     case 'Cukup Baik':
        //         $total += 80;
        //         break;
        //     case 'Tidak Baik':
        //         $total += 70;
        //         break;
        // }

        $total = $total / 5;

        $data['status'] = $nilai_akhir_pure['rata'] > 75 ? 'Lulus' : 'Tidak Lulus';

        // Menggunakan PdfGenerator (periksa pustaka PDF Anda di CI4)
        $pdf = new PdfGenerator();
        $data['title'] = "Detail Nilai";
        $file_pdf = $data['title'];
        $paper = 'A4';
        $orientation = "landscape";
        $html = view('mentor/cetak_detail_riwayat_nilai_bimbingan', $data);  // Menggunakan view() di CI4
        $pdf->generate($html, $file_pdf, $paper, $orientation);
    }

    public function review_surat($encrypt_id)
    {
        // Cek level pengguna dari session (misalnya 'level' menyimpan informasi jenis pengguna)
        $user_level = $this->session->get('level'); // Pastikan 'level' di-set saat login

        if ($user_level !== 'mentor') {
            return view('no_access');
        }
        helper('date');  // Load helper 'date'
        $registrasiModel = new RegistrasiModel();
        $detailRegisModel = new DetailRegisModel();
        $mentorModel = new MentorModel();
        $id = $this->decryptor($encrypt_id);

        // $instansi = $this->request->getPost('instansi');

        // dd($this->request->getPost());

        // Ambil detail data registrasi
        $data['detail'] = $registrasiModel->getDetail($id);
        $data['detail']['encrypt_id'] = $encrypt_id;
        // Ambil detail mentor
        $data['detail_mentor'] = $detailRegisModel->getDetailWithMentor($id);
        // Ambil daftar mentor
        $data['list_mentor'] = [];

        // dd($data['detail_mentor']['nipg'] !== null && $data['detail']['status'] == 'Accept');

        // Ambil daftar mentor dan filter berdasarkan jumlah anak bimbingan
        $all_mentors = $mentorModel->getData();  // Ambil semua mentor
        foreach ($all_mentors as $mentor) {
            $nipg = $mentor['nipg'];

            // Hitung jumlah anak bimbingan berdasarkan nipg
            $count_children = $detailRegisModel->countMentorChildren($nipg);

            // Jika jumlah anak bimbingan kurang dari 2, tambahkan ke list_mentor
            if ($count_children < 2) {
                $data['list_mentor'][] = $mentor;
            }
        }

        // Ambil data timeline dari registrasi
        $data['timeline'] = $registrasiModel->getTimeline($id);

        $id_magang = $this->anakMagangModel->getIdMagangByRegister($id);

        $data['anak_magang'] = $this->anakMagangModel->getPesertaByIdMagang($id_magang);
        // dd($data['anak_magang']);
        // dd($data['detail_mentor']);
        // Split timeline berdasarkan tanda koma (atau tanda lainnya sesuai format)
        if (!empty($data['timeline'])) {
            $data['timeline'] = explode(',', $data['timeline']);
        }

        if (!$data['detail']) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Data tidak ditemukan');
        }
        // dd($data);
        return view('mentor/header')
            . view('mentor/sidebar')
            . view('mentor/topbar')
            . view('mentor/review_surat', $data)
            . view('mentor/footer');
    }
}

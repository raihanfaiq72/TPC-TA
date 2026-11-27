# Modelling pertumbuhan lele v5

Notebook: `Modelling_Pertumbuhan_Lele_v5.ipynb`

Input: dua CSV raw v5 di folder `data/`. Dataset tetap raw; seluruh fitur ringkasan dibuat saat modelling.

Model memprediksi bobot rata-rata pada sampling pertumbuhan berikutnya menggunakan Ridge Regression; fitur hanya memakai interval yang sudah selesai sebelum sampling prediksi dan membandingkannya dengan baseline persistence. Evaluasi menahan siklus secara utuh. Untuk dijalankan, gunakan Python dengan `numpy` dan `pandas` tersedia.

Batasan: input v5 sepenuhnya sintetis. Hasil tidak layak disebut akurasi lapangan. Prediksi satu interval ke depan belum otomatis menjadi ETA panen; ETA di halaman sebaiknya disebut sementara sampai divalidasi dengan pengamatan kolam nyata dan pengujian multi-langkah. Harga lele tidak dimodelkan pada tahap ini.

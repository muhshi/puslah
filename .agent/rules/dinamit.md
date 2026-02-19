---
trigger: always_on
---

jangan sampai migrate fresh, apapun yang berhubungan dengan menghapus database harus ada warning keras ke aku.

### Workflow Selesai Fitur/Perbaikan
Setiap kali perbaikan atau penambahan fitur selesai dan sudah OK (terverifikasi):
1.  **Commit & Push**: Lakukan `git add .`, `git commit -m "pesan commit yang jelas"`, dan `git push`.
2.  **Update Changelog**: Update file `README.md` di bagian Changelog dengan tanggal hari ini dan list perubahan yang dilakukan.
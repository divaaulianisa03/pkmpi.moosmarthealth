function KartuSapi({ sapi, onClick }) {
  // SINKRONISASI 1: Menyesuaikan status utama kartu berdasarkan nilai database aktual (tinggi, sedang, rendah)
  const statusUtama =
    sapi.status_suhu === "danger" || sapi.status_oksigen === "danger" || sapi.level_stress === "tinggi"
      ? "danger"
      : sapi.status_suhu === "warning" || sapi.status_oksigen === "warning" || sapi.level_stress === "sedang"
      ? "warning"
      : "normal";

  const warna = { normal: "#4a7c2f", warning: "#d97706", danger: "#dc2626" };
  const bg = { normal: "#f0fdf4", warning: "#fffbeb", danger: "#fef2f2" };
  const label = { normal: "Normal", warning: "Waspada", danger: "Bahaya" };
  
  // SINKRONISASI 2: Menyesuaikan label teks kestresan agar mengikuti isi kolom database
  const stressLabel = { rendah: "Rendah", sedang: "Sedang", tinggi: "Tinggi" };

  return (
    <div
      className="kartu"
      style={{ borderLeft: `4px solid ${warna[statusUtama]}`, background: bg[statusUtama] }}
      onClick={onClick}
    >
      <div className="kartu-top">
        <div>
          <span className="cow-id">{sapi.cow_id}</span>
          <h3 className="cow-nama">{sapi.nama}</h3>
        </div>
        <span className="badge" style={{ background: warna[statusUtama] }}>
          {label[statusUtama]}
        </span>
      </div>

      <div className="kartu-sensor">
        <div className="sensor-box">
          <div className="sensor-info">
            <span className="sensor-label">Suhu Tubuh</span>
            <span className="sensor-val">{sapi.suhu_celsius}°C</span>
            <span className="sensor-st" style={{ color: warna[sapi.status_suhu] }}>
              {label[sapi.status_suhu]}
            </span>
          </div>
        </div>
        <div className="sensor-box">
          <div className="sensor-info">
            <span className="sensor-label">Kadar Oksigen</span>
            <span className="sensor-val">{sapi.kadar_oksigen_persen}%</span>
            <span className="sensor-st" style={{ color: warna[sapi.status_oksigen] }}>
              {label[sapi.status_oksigen]}
            </span>
          </div>
        </div>
      </div>

      <div className="kartu-aktivitas">
        <span>Tingkat Kestressan</span>
        {/* SINKRONISASI 3: Pewarnaan teks tingkat kestresan berdasarkan nilai aktual */}
        <span 
          className="intensitas" 
          style={{ color: warna[sapi.level_stress === "tinggi" ? "danger" : sapi.level_stress === "sedang" ? "warning" : "normal"] }}
        >
          {stressLabel[sapi.level_stress] || "Rendah"}
        </span>
      </div>
      <div className="kartu-footer">Klik untuk lihat grafik</div>
    </div>
  );
}

export default KartuSapi;
import { useState, useEffect } from "react";
import axios from "axios";

function Riwayat() {
  const warna = { normal: "#4a7c2f", warning: "#d97706", danger: "#dc2626" };
  const label = { normal: "Normal", warning: "Waspada", danger: "Bahaya" };

  const stressWarna = { rendah: "#4a7c2f", sedang: "#d97706", tinggi: "#dc2626" };
  const stressLabel = { rendah: "Rendah", sedang: "Sedang", tinggi: "Tinggi" };

  const formatWaktu = (isoString) => {
    if (!isoString) return "-";
    const d = new Date(isoString);
    return d.toLocaleString("id-ID", {
      day: "2-digit",
      month: "short",
      year: "numeric",
      hour: "2-digit",
      minute: "2-digit",
      second: "2-digit",
    });
  };

  const [riwayat, setRiwayat] = useState([]);
  const [loading, setLoading] = useState(true);
  const [filterSapi, setFilterSapi] = useState("");
  const [limit, setLimit] = useState(100);

  const fetchRiwayat = async () => {
    setLoading(true);
    try {
      const params = { limit };
      if (filterSapi) params.cow_id = filterSapi;

      const res = await axios.get("/api/sensor/history", { params });
      setRiwayat(res.data.data || []);
    } catch (err) {
      console.error("Gagal fetch riwayat:", err);
      setRiwayat([]);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchRiwayat();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [filterSapi, limit]);

  return (
    <div className="riwayat-wrap">
      <div className="riwayat-filter">
        <input
          type="text"
          className="riwayat-input"
          placeholder="Filter ID sapi, misal COW-001"
          value={filterSapi}
          onChange={e => setFilterSapi(e.target.value)}
        />
        <select className="riwayat-select" value={limit} onChange={e => setLimit(Number(e.target.value))}>
          <option value={50}>50 data terakhir</option>
          <option value={100}>100 data terakhir</option>
          <option value={500}>500 data terakhir</option>
          <option value={1000}>1000 data terakhir</option>
        </select>
        <button className="refresh-btn" onClick={fetchRiwayat}>Refresh</button>
        <span className="riwayat-count">{riwayat.length} baris ditampilkan</span>
      </div>

      {loading ? (
        <div className="loading">Memuat riwayat...</div>
      ) : (
        <table className="riwayat-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Nama</th>
              <th>Suhu</th>
              <th>Status Suhu</th>
              <th>Kadar Oksigen</th>
              <th>Status Oksigen</th>
              <th>Aktivitas & Kestresan</th>
              <th>Waktu</th>
            </tr>
          </thead>
          <tbody>
            {riwayat.map(d => (
              <tr key={d.id}>
                <td><span className="cow-id">{d.cow_id}</span></td>
                <td>{d.nama}</td>
                <td><strong>{d.suhu_celsius}°C</strong></td>
                <td><span className="badge-small" style={{ background: warna[d.status_suhu] }}>{label[d.status_suhu]}</span></td>
                <td><strong>{d.kadar_oksigen_persen}%</strong></td>
                <td><span className="badge-small" style={{ background: warna[d.status_oksigen] }}>{label[d.status_oksigen]}</span></td>
                <td>
                  <span style={{ marginRight: "8px", textTransform: "capitalize" }}>
                    {d.nilai_gerakan ?? "-"}
                  </span>
                  <span className="badge-small" style={{ background: stressWarna[d.level_stress] }}>
                    {stressLabel[d.level_stress] || "Rendah"}
                  </span>
                </td>
                <td className="timestamp">{formatWaktu(d.recorded_at)}</td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </div>
  );
}

export default Riwayat;
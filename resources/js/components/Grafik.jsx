import { useEffect, useRef } from "react";

function Grafik({ data, selectedSapi, setSelectedSapi }) {
  const suhuRef = useRef(null);
  const oksigenRef = useRef(null);
  const suhuChart = useRef(null);
  const oksigenChart = useRef(null);

  // SINKRONISASI LABEL: Memetakan teks database (rendah, sedang, tinggi) agar rapi di layar
  const stressLabel = { rendah: "Rendah", sedang: "Sedang", tinggi: "Tinggi" };

  const sapiAktif = selectedSapi
    ? data.find(d => d.cow_id === selectedSapi.cow_id) || data[0]
    : data[0];

  // Plugin generik buat gambar zona warna (background) + garis putus-putus
  // di ambang batas, SEBELUM bar-nya digambar (beforeDatasetsDraw).
  // zones: [{ from, to, color }] dalam satuan nilai sumbu-Y (bukan pixel).
  // lines: [{ value, color }] buat garis ambang batas.
  const buatZonaPlugin = (id, zones, lines) => ({
    id,
    beforeDatasetsDraw(chart) {
      const { ctx, chartArea, scales } = chart;
      const y = scales.y;
      ctx.save();

      zones.forEach(z => {
        const yTop = y.getPixelForValue(z.to);
        const yBottom = y.getPixelForValue(z.from);
        ctx.fillStyle = z.color;
        ctx.fillRect(chartArea.left, yTop, chartArea.right - chartArea.left, yBottom - yTop);
      });

      ctx.setLineDash([4, 4]);
      ctx.lineWidth = 1;
      lines.forEach(l => {
        const yPos = y.getPixelForValue(l.value);
        ctx.strokeStyle = l.color;
        ctx.beginPath();
        ctx.moveTo(chartArea.left, yPos);
        ctx.lineTo(chartArea.right, yPos);
        ctx.stroke();
      });

      ctx.restore();
    }
  });

  // Zona suhu: normal <39.3 (hijau) | waspada 39.3-40.3 (kuning) | bahaya >=40.4 (merah)
  const zonaSuhuPlugin = buatZonaPlugin(
    "zonaSuhu",
    [
      { from: 36, to: 39.3, color: "rgba(74,124,47,0.07)" },
      { from: 39.3, to: 40.4, color: "rgba(217,119,6,0.10)" },
      { from: 40.4, to: 43, color: "rgba(220,38,38,0.08)" },
    ],
    [
      { value: 39.3, color: "#d97706" },
      { value: 40.4, color: "#dc2626" },
    ]
  );

  // Zona oksigen: kebalikan suhu - makin RENDAH makin parah.
  // normal >=95 (hijau) | waspada 90-94 (kuning) | bahaya <90 (merah)
  const zonaOksigenPlugin = buatZonaPlugin(
    "zonaOksigen",
    [
      { from: 70, to: 90, color: "rgba(220,38,38,0.08)" },
      { from: 90, to: 95, color: "rgba(217,119,6,0.10)" },
      { from: 95, to: 100, color: "rgba(74,124,47,0.07)" },
    ],
    [
      { value: 90, color: "#dc2626" },
      { value: 95, color: "#d97706" },
    ]
  );

  useEffect(() => {
    if (!data || data.length === 0) return;

    const warna = { normal: "#4a7c2f", warning: "#d97706", danger: "#dc2626" };

    const buatGrafik = () => {
      if (suhuRef.current) {
        if (suhuChart.current) suhuChart.current.destroy();
        suhuChart.current = new window.Chart(suhuRef.current, {
          type: "bar",
          data: {
            labels: data.map(d => d.nama),
            datasets: [{
              label: "Suhu (°C)",
              data: data.map(d => parseFloat(d.suhu_celsius)),
              backgroundColor: data.map(d => warna[d.status_suhu] + "99"),
              borderColor: data.map(d => warna[d.status_suhu]),
              borderWidth: 2,
              borderRadius: 6,
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
              y: {
                min: 36, max: 43,
                ticks: { callback: v => v + "°C" },
                grid: { color: "#f1f5f9" }
              },
              x: { grid: { display: false } }
            }
          },
          plugins: [zonaSuhuPlugin]
        });
      }

      if (oksigenRef.current) {
        if (oksigenChart.current) oksigenChart.current.destroy();
        oksigenChart.current = new window.Chart(oksigenRef.current, {
          type: "bar",
          data: {
            labels: data.map(d => d.nama),
            datasets: [{
              label: "Kadar Oksigen (%)",
              data: data.map(d => parseInt(d.kadar_oksigen_persen)),
              backgroundColor: data.map(d => warna[d.status_oksigen] + "99"),
              borderColor: data.map(d => warna[d.status_oksigen]),
              borderWidth: 2,
              borderRadius: 6,
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
              y: {
                min: 70, max: 100,
                ticks: { callback: v => v + "%" },
                grid: { color: "#f1f5f9" }
              },
              x: { grid: { display: false } }
            }
          },
          plugins: [zonaOksigenPlugin]
        });
      }
    };

    if (window.Chart) {
      buatGrafik();
    } else {
      const interval = setInterval(() => {
        if (window.Chart) { buatGrafik(); clearInterval(interval); }
      }, 200);
    }

    return () => {
      if (suhuChart.current) suhuChart.current.destroy();
      if (oksigenChart.current) oksigenChart.current.destroy();
    };
  }, [data]);

  return (
    <div>
      <div className="grafik-select-wrap">
        <label className="grafik-label">Pilih Sapi:</label>
        <select
          className="grafik-select"
          value={sapiAktif?.cow_id || ""}
          onChange={e => setSelectedSapi(data.find(d => d.cow_id === e.target.value))}
        >
          {data.map(d => (
            <option key={d.cow_id} value={d.cow_id}>{d.cow_id} - {d.nama}</option>
          ))}
        </select>
      </div>

      {sapiAktif && (
        <div className="grafik-detail">
          <div className="detail-card">
            <div>
              <div className="detail-val">{sapiAktif.suhu_celsius}°C</div>
              <div className="detail-label">Suhu Tubuh</div>
            </div>
          </div>
          <div className="detail-card">
            <div>
              <div className="detail-val">{sapiAktif.kadar_oksigen_persen}%</div>
              <div className="detail-label">Kadar Oksigen</div>
            </div>
          </div>
          <div className="detail-card">
            <div>
              {/* PERUBAHAN: Memakai objek mapping stressLabel agar teks tampil rapi dengan huruf kapital */}
              <div className="detail-val">
                {stressLabel[sapiAktif.level_stress] || "Rendah"}
              </div>
              <div className="detail-label">Tingkat Kestressan</div>
            </div>
          </div>
        </div>
      )}

      <div className="grafik-grid">
        <div className="grafik-card">
          <h3 className="grafik-title">Suhu Tubuh Semua Sapi</h3>
          <div style={{ position: "relative", height: "260px" }}>
            <canvas ref={suhuRef} role="img" aria-label="Grafik suhu tubuh sapi dengan zona ambang batas normal, waspada, bahaya"></canvas>
          </div>
        </div>
        <div className="grafik-card">
          <h3 className="grafik-title">Kadar Oksigen Semua Sapi</h3>
          <div style={{ position: "relative", height: "260px" }}>
            <canvas ref={oksigenRef} role="img" aria-label="Grafik kadar oksigen sapi dengan zona ambang batas normal, waspada, bahaya"></canvas>
          </div>
        </div>
      </div>
    </div>
  );
}

export default Grafik;
<template>
  <!-- Kartu CR80: 85.6mm × 53.98mm -->
  <div class="student-card" :id="`card-${student.id}`">
    <!-- Header dengan gradient navy -->
    <div class="card-header">
      <div class="school-logo">
        <svg viewBox="0 0 24 24" fill="white" class="logo-icon">
          <path d="M12 3L1 9l4 2.18v6L12 21l7-3.82v-6l2-1.09V17h2V9L12 3zm6.82 6L12 12.72 5.18 9 12 5.28 18.82 9zM17 15.99l-5 2.73-5-2.73v-3.72L12 15l5-2.73v3.72z"/>
        </svg>
      </div>
      <div class="school-info">
        <p class="school-name">SMK NEGERI 1</p>
        <p class="school-tagline">SMART ATTENDANCE SYSTEM</p>
      </div>
    </div>

    <!-- Body -->
    <div class="card-body">
      <!-- Foto placeholder -->
      <div class="photo-section">
        <div class="photo-placeholder">
          <svg viewBox="0 0 24 24" fill="#cbd5e1" class="w-full h-full">
            <path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/>
          </svg>
        </div>
      </div>

      <!-- Info siswa -->
      <div class="info-section">
        <p class="student-name">{{ student.name }}</p>
        <div class="info-row">
          <span class="info-label">NIS</span>
          <span class="info-value">{{ student.nis }}</span>
        </div>
        <div class="info-row">
          <span class="info-label">Kelas</span>
          <span class="info-value">{{ student.class_name }}</span>
        </div>
        <div class="info-row">
          <span class="info-label">Status</span>
          <span class="info-value capitalize">{{ student.status }}</span>
        </div>
      </div>

      <!-- QR Code -->
      <div class="qr-section">
        <canvas :id="`qr-${student.id}`" class="qr-canvas"></canvas>
        <p class="qr-label">Scan untuk Absensi</p>
      </div>
    </div>

    <!-- Footer -->
    <div class="card-footer">
      <p>Berlaku selama menjadi siswa aktif</p>
    </div>
  </div>
</template>

<script setup>
import { onMounted } from 'vue'
import QRCode from 'qrcode'

const props = defineProps({
  student: { type: Object, required: true }
})

onMounted(async () => {
  if (props.student.qr_code) {
    const canvas = document.getElementById(`qr-${props.student.id}`)
    if (canvas) {
      await QRCode.toCanvas(canvas, props.student.qr_code, {
        width: 80,
        margin: 0,
        color: { dark: '#0f2340', light: '#ffffff' }
      })
    }
  }
})
</script>

<style scoped>
/* Dimensi CR80: 85.6mm × 53.98mm */
.student-card {
  width: 85.6mm;
  height: 53.98mm;
  background: #ffffff;
  border-radius: 3mm;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  font-family: 'Inter', sans-serif;
  box-shadow: 0 4px 20px rgba(0,0,0,0.15);
  border: 1px solid #e2e8f0;
}

.card-header {
  background: linear-gradient(135deg, #0f2340, #1e3a5f);
  padding: 3mm 4mm;
  display: flex;
  align-items: center;
  gap: 3mm;
}

.school-logo {
  width: 8mm;
  height: 8mm;
  background: rgba(255,255,255,0.2);
  border-radius: 1.5mm;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.logo-icon {
  width: 5mm;
  height: 5mm;
}

.school-info {
  flex: 1;
}

.school-name {
  color: white;
  font-size: 3.2mm;
  font-weight: 700;
  line-height: 1.2;
}

.school-tagline {
  color: rgba(255,255,255,0.6);
  font-size: 2mm;
  font-weight: 400;
  letter-spacing: 0.3mm;
  text-transform: uppercase;
}

.card-body {
  flex: 1;
  display: flex;
  padding: 2.5mm 3mm;
  gap: 3mm;
  align-items: center;
}

.photo-section {
  flex-shrink: 0;
}

.photo-placeholder {
  width: 16mm;
  height: 20mm;
  background: #f1f5f9;
  border-radius: 1.5mm;
  border: 0.3mm solid #e2e8f0;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}

.info-section {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 1.5mm;
  min-width: 0;
}

.student-name {
  font-size: 3.5mm;
  font-weight: 700;
  color: #0f2340;
  line-height: 1.2;
  word-break: break-word;
}

.info-row {
  display: flex;
  gap: 1.5mm;
  align-items: center;
}

.info-label {
  font-size: 2mm;
  color: #94a3b8;
  font-weight: 600;
  text-transform: uppercase;
  width: 10mm;
  flex-shrink: 0;
}

.info-value {
  font-size: 2.5mm;
  color: #334155;
  font-weight: 500;
}

.qr-section {
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1mm;
}

.qr-canvas {
  width: 18mm !important;
  height: 18mm !important;
}

.qr-label {
  font-size: 1.8mm;
  color: #94a3b8;
  text-align: center;
}

.card-footer {
  background: linear-gradient(135deg, #0f2340, #1e3a5f);
  padding: 1.5mm 4mm;
  text-align: center;
}

.card-footer p {
  color: rgba(255,255,255,0.5);
  font-size: 1.8mm;
  letter-spacing: 0.2mm;
}

@media print {
  .student-card {
    box-shadow: none;
    page-break-inside: avoid;
  }
}
</style>

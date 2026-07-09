import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/authStore'

// Lazy-load semua views
const LoginView        = () => import('@/views/auth/LoginView.vue')
const HomeView         = () => import('@/views/dashboard/HomeView.vue')
const StudentsView     = () => import('@/views/students/StudentsView.vue')
const StudentCardPrint = () => import('@/views/students/StudentCardPrint.vue')
const TeachersView     = () => import('@/views/teachers/TeachersView.vue')
const ClassesView      = () => import('@/views/classes/ClassesView.vue')
const SchedulesView    = () => import('@/views/schedules/SchedulesView.vue')
const MonitorView      = () => import('@/views/attendance/MonitorView.vue')
const ReportView       = () => import('@/views/attendance/ReportView.vue')
const NotFoundView     = () => import('@/views/NotFoundView.vue')

const routes = [
  {
    path: '/login',
    name: 'login',
    component: LoginView,
    meta: { layout: 'auth', public: true }
  },
  {
    path: '/',
    redirect: '/dashboard'
  },
  {
    path: '/dashboard',
    name: 'dashboard',
    component: HomeView,
    meta: { title: 'Dashboard', icon: 'home' }
  },
  {
    path: '/students',
    name: 'students',
    component: StudentsView,
    meta: { title: 'Data Siswa', icon: 'users' }
  },
  {
    path: '/students/card-print',
    name: 'student-card-print',
    component: StudentCardPrint,
    meta: { layout: 'print', public: false }
  },
  {
    path: '/teachers',
    name: 'teachers',
    component: TeachersView,
    meta: { title: 'Data Guru', icon: 'academic-cap' }
  },
  {
    path: '/classes',
    name: 'classes',
    component: ClassesView,
    meta: { title: 'Kelas & Mapel', icon: 'book-open' }
  },
  {
    path: '/schedules',
    name: 'schedules',
    component: SchedulesView,
    meta: { title: 'Jadwal Pelajaran', icon: 'calendar' }
  },
  {
    path: '/attendance/monitor',
    name: 'monitor',
    component: MonitorView,
    meta: { title: 'Monitor Absensi', icon: 'eye' }
  },
  {
    path: '/attendance/report',
    name: 'report',
    component: ReportView,
    meta: { title: 'Laporan', icon: 'chart-bar' }
  },
  {
    path: '/:pathMatch(.*)*',
    name: 'not-found',
    component: NotFoundView
  }
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes
})

// ── Navigation Guard ──
router.beforeEach((to, from, next) => {
  const authStore = useAuthStore()

  // Halaman publik (login) — tidak perlu token
  if (to.meta.public) {
    // Jika sudah login, redirect ke dashboard
    if (authStore.isLoggedIn) return next('/dashboard')
    return next()
  }

  // Halaman terproteksi — cek token
  if (!authStore.isLoggedIn) {
    return next({ name: 'login', query: { redirect: to.fullPath } })
  }

  // Mencegah guru mengakses dashboard
  if (authStore.user && authStore.user.role === 'guru') {
    authStore.logout()
    return next({ name: 'login' })
  }

  next()
})

export default router

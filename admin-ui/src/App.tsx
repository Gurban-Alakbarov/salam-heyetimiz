import { Route, Routes } from 'react-router-dom'
import { PERM } from '@/auth/permissions'
import { ProtectedRoute, PublicRoute, RequirePermission } from '@/auth/guards'
import { AppLayout } from '@/layout/AppLayout'
import { AccountPage } from '@/pages/AccountPage'
import { DashboardPage } from '@/pages/DashboardPage'
import { ForbiddenPage } from '@/pages/ForbiddenPage'
import { NotFoundPage } from '@/pages/NotFoundPage'
import { AccessControlPage } from '@/pages/access/AccessControlPage'
import { AdminsPage } from '@/pages/admins/AdminsPage'
import { AuditPage } from '@/pages/audit/AuditPage'
import { ComplexDetailPage } from '@/pages/complexes/ComplexDetailPage'
import { ComplexesPage } from '@/pages/complexes/ComplexesPage'
import { ResidentsPage } from '@/pages/residents/ResidentsPage'
import { SecurityPage } from '@/pages/security/SecurityPage'
import { SettingsPage } from '@/pages/settings/SettingsPage'
import { LoginPage } from '@/pages/auth/LoginPage'
import { TwoFactorPage } from '@/pages/auth/TwoFactorPage'
import { DeviceDetailPage } from '@/pages/devices/DeviceDetailPage'
import { DeviceFormPage } from '@/pages/devices/DeviceFormPage'
import { DevicesPage } from '@/pages/devices/DevicesPage'
import { CampaignDetailPage } from '@/pages/notifications/CampaignDetailPage'
import { CampaignsPage } from '@/pages/notifications/CampaignsPage'
import { CreateCampaignPage } from '@/pages/notifications/CreateCampaignPage'
import { OrderDetailPage } from '@/pages/orders/OrderDetailPage'
import { OrdersPage } from '@/pages/orders/OrdersPage'
import { PaymentLogsPage } from '@/pages/payments/PaymentLogsPage'
import { RefundsPage } from '@/pages/refunds/RefundsPage'
import { SubscriptionsPage } from '@/pages/subscriptions/SubscriptionsPage'

export function App() {
  return (
    <Routes>
      <Route element={<PublicRoute />}>
        <Route path="/login" element={<LoginPage />} />
        <Route path="/login/2fa" element={<TwoFactorPage />} />
      </Route>

      <Route element={<ProtectedRoute />}>
        <Route element={<AppLayout />}>
          <Route path="/" element={<RequirePermission anyOf={[PERM.dashboardView]}><DashboardPage /></RequirePermission>} />
          <Route path="/complexes" element={<RequirePermission anyOf={[PERM.complexesView]}><ComplexesPage /></RequirePermission>} />
          <Route path="/complexes/:id" element={<RequirePermission anyOf={[PERM.complexesView]}><ComplexDetailPage /></RequirePermission>} />
          <Route path="/residents" element={<RequirePermission anyOf={[PERM.residentsView]}><ResidentsPage /></RequirePermission>} />
          <Route path="/access" element={<RequirePermission anyOf={[PERM.accessManage]}><AccessControlPage /></RequirePermission>} />
          <Route path="/devices" element={<RequirePermission anyOf={[PERM.devicesView]}><DevicesPage /></RequirePermission>} />
          <Route path="/devices/new" element={<RequirePermission anyOf={[PERM.devicesCreate]}><DeviceFormPage /></RequirePermission>} />
          <Route path="/devices/:id" element={<RequirePermission anyOf={[PERM.devicesView]}><DeviceDetailPage /></RequirePermission>} />
          <Route path="/devices/:id/edit" element={<RequirePermission anyOf={[PERM.devicesUpdate]}><DeviceFormPage /></RequirePermission>} />
          <Route path="/subscriptions" element={<RequirePermission anyOf={[PERM.subscriptionsView]}><SubscriptionsPage /></RequirePermission>} />
          <Route path="/orders" element={<RequirePermission anyOf={[PERM.ordersView]}><OrdersPage /></RequirePermission>} />
          <Route path="/orders/:id" element={<RequirePermission anyOf={[PERM.ordersView]}><OrderDetailPage /></RequirePermission>} />
          <Route path="/payment-logs" element={<RequirePermission anyOf={[PERM.ordersView]}><PaymentLogsPage /></RequirePermission>} />
          <Route path="/refunds" element={<RequirePermission anyOf={[PERM.refundsView]}><RefundsPage /></RequirePermission>} />
          <Route path="/notifications" element={<RequirePermission anyOf={[PERM.notificationsView]}><CampaignsPage /></RequirePermission>} />
          <Route path="/notifications/new" element={<RequirePermission anyOf={[PERM.notificationsSend]}><CreateCampaignPage /></RequirePermission>} />
          <Route path="/notifications/:id" element={<RequirePermission anyOf={[PERM.notificationsView]}><CampaignDetailPage /></RequirePermission>} />
          <Route path="/admins" element={<RequirePermission anyOf={[PERM.adminsView]}><AdminsPage /></RequirePermission>} />
          <Route path="/audit" element={<RequirePermission anyOf={[PERM.auditView]}><AuditPage /></RequirePermission>} />
          <Route path="/settings" element={<RequirePermission anyOf={[PERM.systemSettings]}><SettingsPage /></RequirePermission>} />
          <Route path="/traccar" element={<RequirePermission anyOf={[PERM.systemSettings]}><SettingsPage defaultTab="traccar" /></RequirePermission>} />
          <Route path="/security" element={<RequirePermission anyOf={[PERM.systemSettings]}><SecurityPage /></RequirePermission>} />
          <Route path="/account" element={<AccountPage />} />
          <Route path="/403" element={<ForbiddenPage />} />
          <Route path="*" element={<NotFoundPage />} />
        </Route>
      </Route>
    </Routes>
  )
}

import type { OrderPurpose } from '@/types/api'

export const purposeLabels: Record<OrderPurpose, string> = {
  device_sale: 'Cihaz satışı',
  sub_main: 'Əsas abunə',
  sub_additional: 'Əlavə abunə',
  sub_renewal: 'Abunə yeniləməsi',
  bundle: 'Paket',
}

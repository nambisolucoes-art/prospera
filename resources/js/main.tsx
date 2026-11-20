import { createRoot } from 'react-dom/client';
import ApiApplication from '@/app/ApiApplication';
import AppLoader from '@/components/AppLoader/AppLoader';
import { initWaves } from '@/utils/initWaves';

initWaves();

const container = document.getElementById('root');
if (container) {
  createRoot(container).render(
    <>
      <AppLoader />
      <ApiApplication />
    </>,
  );
}

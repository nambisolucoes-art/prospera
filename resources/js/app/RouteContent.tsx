import { lazy } from 'react';
import { pages } from '@/pages/Pages/Pages.data';
import { extras } from '@/pages/Extras/Extras.data';
import { applications } from '@/pages/Applications/Applications.data';
import { layouts } from '@/pages/Layouts/Layouts.data';
import { crmPages } from '@/pages/Crm/Crm.data';
import { ecommercePages } from '@/pages/Ecommerce/Ecommerce.data';

const DashboardOne = lazy(() => import('@/pages/DashboardOne/DashboardOne'));
const DashboardThree = lazy(() => import('@/pages/DashboardThree/DashboardThree'));
const DashboardTwo = lazy(() => import('@/pages/DashboardTwo/DashboardTwo'));
const Buttons = lazy(() => import('@/pages/Buttons/Buttons'));
const Panels = lazy(() => import('@/pages/Panels/Panels'));
const Portlets = lazy(() => import('@/pages/Portlets/Portlets'));
const CheckboxRadio = lazy(() => import('@/pages/CheckboxRadio/CheckboxRadio'));
const TabsPage = lazy(() => import('@/pages/TabsPage/TabsPage'));
const Modals = lazy(() => import('@/pages/Modals/Modals'));
const ProgressBars = lazy(() => import('@/pages/ProgressBars/ProgressBars'));
const Notifications = lazy(() => import('@/pages/Notifications/Notifications'));
const Images = lazy(() => import('@/pages/Images/Images'));
const Carousels = lazy(() => import('@/pages/Carousels/Carousels'));
const BootstrapElements = lazy(() => import('@/pages/BootstrapElements/BootstrapElements'));
const Typography = lazy(() => import('@/pages/Typography/Typography'));
const ComponentsGrid = lazy(() => import('@/pages/ComponentsGrid/ComponentsGrid'));
const ComponentsWidgets = lazy(() => import('@/pages/ComponentsWidgets/ComponentsWidgets'));
const ComponentsNestable = lazy(() => import('@/pages/ComponentsNestable/ComponentsNestable'));
const ComponentsRangeSliders = lazy(() => import('@/pages/ComponentsRangeSliders/ComponentsRangeSliders'));
const ComponentsAnimation = lazy(() => import('@/pages/ComponentsAnimation/ComponentsAnimation'));
const ComponentsSweetAlert = lazy(() => import('@/pages/ComponentsSweetAlert/ComponentsSweetAlert'));
const IconsPage = lazy(() => import('@/pages/IconsPage/IconsPage'));
const FormAdvanced = lazy(() => import('@/pages/FormAdvanced/FormAdvanced'));
const FormElements = lazy(() => import('@/pages/FormElements/FormElements'));
const FormPickers = lazy(() => import('@/pages/FormPickers/FormPickers'));
const FormWizardPage = lazy(() => import('@/pages/FormWizardPage/FormWizardPage'));
const FormMasks = lazy(() => import('@/pages/FormMasks/FormMasks'));
const SummernotePage = lazy(() => import('@/pages/SummernotePage/SummernotePage'));
const WysiwigPage = lazy(() => import('@/pages/WysiwigPage/WysiwigPage'));
const TablesPage = lazy(() => import('@/pages/TablesPage/TablesPage'));
const ChartsPage = lazy(() => import('@/pages/ChartsPage/ChartsPage'));
const MapsPage = lazy(() => import('@/pages/MapsPage/MapsPage'));
const ImageCropPage = lazy(() => import('@/pages/ImageCropPage/ImageCropPage'));
const UploadsPage = lazy(() => import('@/pages/UploadsPage/UploadsPage'));
const XEditablePage = lazy(() => import('@/pages/XEditablePage/XEditablePage'));
const FormValidation = lazy(() => import('@/pages/FormValidation/FormValidation'));
const Pages = lazy(() => import('@/pages/Pages/Pages'));
const Extras = lazy(() => import('@/pages/Extras/Extras'));
const Applications = lazy(() => import('@/pages/Applications/Applications'));
const Layouts = lazy(() => import('@/pages/Layouts/Layouts'));
const Crm = lazy(() => import('@/pages/Crm/Crm'));
const Ecommerce = lazy(() => import('@/pages/Ecommerce/Ecommerce'));

const staticPages: Readonly<Record<string, React.ComponentType>> = {
  'dashboard-2': DashboardTwo,
  'dashboard-3': DashboardThree,
  'dashboard-1': DashboardOne,
  'ui-buttons': Buttons,
  'ui-panels': Panels,
  'ui-portlets': Portlets,
  'ui-checkbox-radio': CheckboxRadio,
  'ui-tabs': TabsPage,
  'ui-modals': Modals,
  'ui-progressbars': ProgressBars,
  'ui-notification': Notifications,
  'ui-images': Images,
  'ui-carousel': Carousels,
  'ui-bootstrap': BootstrapElements,
  'ui-typography': Typography,
  'components-grid': ComponentsGrid,
  'components-widgets': ComponentsWidgets,
  'components-nestable': ComponentsNestable,
  'components-range-sliders': ComponentsRangeSliders,
  'components-animation': ComponentsAnimation,
  'components-sweet-alert': ComponentsSweetAlert,
  'form-elements': FormElements,
  'form-advanced': FormAdvanced,
  'form-pickers': FormPickers,
  'form-validation': FormValidation,
  'form-wizard': FormWizardPage,
  'form-mask': FormMasks,
  'form-summernote': SummernotePage,
  'form-wysiwig': WysiwigPage,
  'form-image-crop': ImageCropPage,
  'form-uploads': UploadsPage,
  'form-xeditable': XEditablePage,
};

export default function RouteContent({ pathname, pageKey }: { pathname: string; pageKey: string }) {
  const filename = pathname.split('/').pop() ?? '';
  const page = pages[filename];
  const extra = extras[filename];
  const application = applications[filename];
  const layout = layouts[filename];
  const crm = crmPages[filename];
  const ecommerce = ecommercePages[filename];

  if (page) return <Pages key={page.key} page={page} />;
  if (extra) return <Extras key={extra.key} page={extra} />;
  if (application) return <Applications key={application.kind} page={application} />;
  if (layout) return <Layouts key={layout.key} page={layout} />;
  if (crm) return <Crm key={crm.key} page={crm} />;
  if (ecommerce) return <Ecommerce key={ecommerce.key} page={ecommerce} />;

  if (pageKey.startsWith('icons-')) return <IconsPage pageKey={pageKey} />;
  if (pageKey.startsWith('chart-')) return <ChartsPage key={pageKey} variant={pageKey.slice(6)} />;
  if (pageKey.startsWith('tables-')) return <TablesPage key={pageKey} variant={pageKey.slice(7)} />;
  if (pageKey === 'map-google') return <MapsPage variant="google" />;
  if (pageKey === 'map-vector') return <MapsPage variant="vector" />;

  const Page = staticPages[pageKey];
  return Page ? <Page /> : null;
}

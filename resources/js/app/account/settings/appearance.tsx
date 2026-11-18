import Button from '@/components/Button/Button';
import { useContext } from 'react';
import { SettingsSidebarContext } from '@/app/account/SettingsLayout';
import { BusinessFormSection } from '@/components/BusinessForm/BusinessForm';

export default function Appearance() {
  const sidebar = useContext(SettingsSidebarContext);
  if (!sidebar) return null;
  const pressed = sidebar.mobile ? sidebar.mobileOpen : sidebar.collapsed;

  return (
    <BusinessFormSection as="section" title="Menu lateral" tourTarget="appearance-sidebar">
      <Button tone="primary" type="button" onClick={sidebar.toggle} aria-pressed={pressed}>
        {sidebar.mobile ? 'Alternar menu lateral' : 'Recolher menu lateral'}
      </Button>
    </BusinessFormSection>
  );
}

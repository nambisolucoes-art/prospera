import Button from '@/components/Button/Button';
import CardBox from '@/components/CardBox/CardBox';
import Label from '@/components/Label/Label';
import SaasLayout from './SaasLayout';
import './Institutional.css';

type Owner = {
  code: string;
  name: string;
  active: boolean;
  covered: boolean;
  units: { code: string; name: string; active: boolean; primary: boolean }[];
};

// Entrada da biblioteca de mídia: as empresas e os estabelecimentos que o acesso alcança, sem escolha prévia.
export default function MediaOwners({ owners }: { owners: Owner[] }) {
  return (
    <SaasLayout tenant title="Mídia" breadcrumb={[{ label: 'Institucional' }, { label: 'Mídia' }]}>
      <div data-tour="media-owners">
        {owners.length === 0 && (
          <CardBox><p className="institutional__empty">Nenhuma empresa ou estabelecimento que o seu acesso alcance.</p></CardBox>
        )}
        {owners.map((owner) => (
          <CardBox key={owner.code}>
            <section className="institutional__section" aria-labelledby={`media-owner-${owner.code}`}>
              <header className="institutional__section-header">
                <h3 id={`media-owner-${owner.code}`} className="institutional__section-title">{owner.name}</h3>
                <div className="institutional__badges">
                  <Label variant="primary">Empresa</Label>
                  {!owner.active && <Label variant="danger">Inativa</Label>}
                </div>
              </header>
              <div className="institutional__actions">
                {owner.covered && (
                  <Button href={`@/empresa/${owner.code}/midia`} tone="primary" labelIconClass="fa fa-picture-o">
                    Biblioteca da empresa
                  </Button>
                )}
              </div>
              {owner.units.length > 0 && (
                <ul className="institutional__units">
                  {owner.units.map((unit) => (
                    <li key={unit.code}>
                      <Button href={`@/estabelecimento/${unit.code}/midia`} tone="white" size="sm">
                        {unit.name}{unit.primary ? ' (principal)' : ''}{unit.active ? '' : ' (inativo)'}
                      </Button>
                    </li>
                  ))}
                </ul>
              )}
            </section>
          </CardBox>
        ))}
      </div>
    </SaasLayout>
  );
}

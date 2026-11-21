import { useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { BusinessFormRow, BusinessFormSection } from '@/components/BusinessForm/BusinessForm';
import { Field, Select, type Errors, type FieldProps } from '@/components/BusinessForm/BusinessFormFields';
import type { Address } from './ClientForm.types';

export type Mask = 'cpf' | 'cnpj' | 'cep' | 'phone';

export function formatMasked(value: string, mask: Mask): string {
  const digits = value.replace(/\D/g, '');
  const pattern = { cpf: '000.000.000-00', cnpj: '00.000.000/0000-00', cep: '00000-000', phone: digits.length > 10 ? '(00) 00000-0000' : '(00) 0000-0000' }[mask];
  let index = 0;
  let result = '';
  for (const character of pattern) {
    if (index >= digits.length) break;
    result += character === '0' ? digits[index++] : character;
  }
  return result;
}

export function MaskedField({ mask, defaultValue = '', ...props }: FieldProps & { mask: Mask }) {
  const [value, setValue] = useState(String(defaultValue));
  return <Field {...props} compact value={formatMasked(value, mask)} inputMode="numeric" onChange={(event) => setValue(event.target.value)} />;
}

export function LookupButton({ label, loading, onClick }: { label: string; loading: boolean; onClick: () => void }) {
  return (
    <button type="button" className="inputGroup__button waves-effect" title={label} aria-label={label} aria-busy={loading} disabled={loading} onClick={onClick}>
      <i className={loading ? 'fa fa-spinner fa-spin' : 'fa fa-search'} aria-hidden="true" />
    </button>
  );
}

const stateOptions = [
  { value: '', label: 'Selecione o estado' },
  ...Object.entries({
    AC: 'Acre', AL: 'Alagoas', AP: 'Amapá', AM: 'Amazonas', BA: 'Bahia', CE: 'Ceará',
    DF: 'Distrito Federal', ES: 'Espírito Santo', GO: 'Goiás', MA: 'Maranhão',
    MT: 'Mato Grosso', MS: 'Mato Grosso do Sul', MG: 'Minas Gerais', PA: 'Pará',
    PB: 'Paraíba', PR: 'Paraná', PE: 'Pernambuco', PI: 'Piauí', RJ: 'Rio de Janeiro',
    RN: 'Rio Grande do Norte', RS: 'Rio Grande do Sul', RO: 'Rondônia', RR: 'Roraima',
    SC: 'Santa Catarina', SP: 'São Paulo', SE: 'Sergipe', TO: 'Tocantins',
  }).map(([value, name]) => ({ value, label: `${name} (${value})` })),
];

type Municipality = [code: string, name: string, state: string];

export function AddressFields({ initial, errors, namePrefix = 'details[address]', errorPrefix = 'details.address', title = 'Informações de endereço', tourTarget, embedded = false }: { initial?: Address; errors: Errors; namePrefix?: string; errorPrefix?: string; title?: string; tourTarget?: string; embedded?: boolean }) {
  const [address, setAddress] = useState<Address>(initial ?? {});
  const [loading, setLoading] = useState(false);
  const [lookupError, setLookupError] = useState('');
  const version = useRef(0);
  const [municipalities, setMunicipalities] = useState<Municipality[]>();
  const cityOptions = useMemo(() => municipalities?.filter(([, , state]) => !address.state || state === address.state)
    .map(([value, city, state]) => ({ value, label: address.state ? city : `${city} (${state})` })), [municipalities, address.state]);
  const cityCode = municipalities?.find(([, city, state]) => city === address.city && state === address.state)?.[0];

  useEffect(() => {
    const request = new AbortController();
    void fetch('/api/localidades/municipios', { signal: request.signal })
      .then((response) => response.ok ? response.json() as Promise<Municipality[]> : Promise.reject())
      .then(setMunicipalities)
      .catch(() => undefined);
    return () => request.abort();
  }, []);

  function update(key: keyof Address, value: string) {
    version.current++;
    setAddress((current) => ({ ...current, [key]: value }));
    setLookupError('');
  }

  async function lookup() {
    const cep = (address.postal_code ?? '').replace(/[\s-]/g, '');
    if (!/^\d{8}$/.test(cep)) {
      setLookupError('Informe um CEP com 8 dígitos.');
      return;
    }
    const request = ++version.current;
    setLoading(true);
    setLookupError('');
    try {
      const response = await fetch(`https://viacep.com.br/ws/${cep}/json/`, { signal: AbortSignal.timeout(8000) });
      if (!response.ok) throw new Error();
      const data: { erro?: boolean; logradouro?: string; bairro?: string; localidade?: string; uf?: string; ibge?: string } = await response.json();
      if (request !== version.current) return;
      if (data.erro) {
        setLookupError('CEP não encontrado.');
      } else {
        const municipality = municipalities?.find(([code]) => code === data.ibge);
        setAddress((current) => ({
          ...current, street: data.logradouro || current.street, neighborhood: data.bairro || current.neighborhood,
          city: municipality?.[1] || data.localidade || current.city, state: municipality?.[2] || data.uf || current.state,
        }));
      }
    } catch {
      if (request === version.current) setLookupError('Consulta indisponível. Preencha o endereço manualmente.');
    } finally {
      setLoading(false);
    }
  }

  const field = (key: keyof Address, label: string, inputMode?: 'numeric', action?: ReactNode) => (
    <Field name={`${namePrefix}[${key}]`} label={label} value={key === 'postal_code' ? formatMasked(address[key] ?? '', 'cep') : address[key] ?? ''} inputMode={inputMode}
      action={action} compact={key === 'postal_code'} autoComplete={key === 'postal_code' ? 'postal-code' : undefined}
      error={errors[`${errorPrefix}.${key}`] || (key === 'postal_code' ? lookupError : undefined)}
      onChange={(event) => update(key, event.target.value)} />
  );

  const fields = (
    <>
      {field('postal_code', 'CEP', 'numeric',
        <LookupButton label="Buscar CEP" loading={loading} onClick={() => void lookup()} />
      )}
      <div>
        <BusinessFormRow>
          <Select label="Estado" name={`${namePrefix}[state]`} searchable options={stateOptions} value={address.state ?? ''}
            error={errors[`${errorPrefix}.state`]} onChange={(value) => {
              version.current++;
              setAddress((current) => ({ ...current, state: value, city: current.state === value ? current.city : '' }));
            }} />
          {cityOptions ? <>
            <input type="hidden" name={`${namePrefix}[city]`} value={address.city ?? ''} />
            <Select label="Cidade" searchable maxVisible={100}
              options={[{ value: '', label: 'Selecione a cidade' }, ...(address.city && !cityCode ? [{ value: 'legacy', label: address.city }] : []), ...cityOptions]}
              value={cityCode ?? (address.city ? 'legacy' : '')} error={errors[`${errorPrefix}.city`]}
              onChange={(value) => {
                if (value === 'legacy') return;
                version.current++;
                const selected = municipalities?.find(([code]) => code === value);
                setAddress((current) => ({ ...current, city: selected?.[1] ?? '', state: selected?.[2] ?? current.state }));
              }} />
          </> : field('city', 'Cidade')}
        </BusinessFormRow>
        {field('neighborhood', 'Bairro')}
        {field('street', 'Logradouro')}
        <BusinessFormRow>{field('number', 'Número')}{field('complement', 'Complemento')}</BusinessFormRow>
      </div>
    </>
  );

  return embedded ? fields : <BusinessFormSection title={title} tourTarget={tourTarget}>{fields}</BusinessFormSection>;
}


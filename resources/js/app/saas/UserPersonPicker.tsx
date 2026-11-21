import { useState } from 'react';
import { Field, Select } from '@/components/BusinessForm/BusinessFormFields';
import FormHelp from '@/components/FormHelp/FormHelp';
import { apiRequest } from '@/utils/apiRequest';
import { LookupButton } from './ClientFormFields';

export type PersonCandidate = { id: number; name: string; email: string | null };

export default function UserPersonPicker({ value, onChange, error }: {
  value?: PersonCandidate;
  onChange: (person: PersonCandidate | undefined) => void;
  error?: string;
}) {
  const [search, setSearch] = useState('');
  const [results, setResults] = useState<PersonCandidate[]>();
  const [loading, setLoading] = useState(false);
  const [failed, setFailed] = useState(false);

  async function findPeople() {
    const query = search.trim();
    if (!query || loading) return;

    setLoading(true);
    setFailed(false);
    try {
      const response = await apiRequest<{ people: PersonCandidate[] }>(
        `/conta/usuarios/pessoas?q=${encodeURIComponent(query)}`,
      );
      setResults(response.people);
    } catch {
      setResults(undefined);
      setFailed(true);
    } finally {
      setLoading(false);
    }
  }

  return <>
    <Field label="Buscar cadastro" value={search} disabled={loading} onChange={(event) => setSearch(event.target.value)}
      action={<LookupButton label="Buscar cadastro" loading={loading}
        onClick={() => void findPeople()} />} />
    <Select label="Cadastro encontrado" name="person_id" required value={value ? String(value.id) : ''}
      placeholder="Selecione um cadastro" error={error}
      options={(results ?? []).map((person) => ({
        value: String(person.id), label: person.email ? `${person.name} (${person.email})` : person.name,
      }))} onChange={(id) => onChange(results?.find((person) => String(person.id) === id))} />
    {failed && <FormHelp>Não foi possível buscar os cadastros.</FormHelp>}
    {results?.length === 0 && <FormHelp>Nenhum cadastro disponível para este acesso.</FormHelp>}
  </>;
}

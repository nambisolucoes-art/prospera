import { useRef, useState, type Dispatch, type SetStateAction } from 'react';

type SetText = Dispatch<SetStateAction<string>>;

export function useCnpjLookup(setName: SetText, setTradeName: SetText) {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const version = useRef(0);

  function reset() {
    version.current++;
    setLoading(false);
    setError('');
  }

  async function lookup(document: string) {
    const cnpj = document.replace(/[./\s-]/g, '');
    if (!/^\d{14}$/.test(cnpj)) {
      setError('Para pesquisar, informe um CNPJ numérico completo. Você pode preencher os nomes manualmente.');
      return;
    }

    const request = ++version.current;
    setLoading(true);
    setError('');

    try {
      const response = await fetch(`https://brasilapi.com.br/api/cnpj/v1/${cnpj}`, {
        signal: AbortSignal.timeout(8000),
      });

      if (request !== version.current) return;
      if (response.status === 404) {
        setError('CNPJ não encontrado.');
        return;
      }
      if (!response.ok) throw new Error();

      const details: { razao_social?: string; nome_fantasia?: string } = await response.json();
      if (request !== version.current) return;

      setName((current) => current.trim() ? current : details.razao_social ?? '');
      setTradeName((current) => current.trim() ? current : details.nome_fantasia ?? '');
    } catch {
      if (request === version.current) setError('Consulta indisponível. Preencha os dados da empresa manualmente.');
    } finally {
      if (request === version.current) setLoading(false);
    }
  }

  return { lookup, reset, loading, error };
}

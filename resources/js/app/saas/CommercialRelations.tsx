import Table from '@/components/Table/Table';
import StatusLabel from '@/components/StatusLabel/StatusLabel';
import type { PersonRelation, RelationStatus } from './Directory.types';
import './CommercialRelations.css';

// Situação de um papel comercial numa empresa. "Sem acesso" não revela se a relação existe.
export function RelationStatusLabel({ status }: { status: RelationStatus | null }) {
  if (status === 'active' || status === 'inactive') return <StatusLabel active={status === 'active'} masculine />;
  return <span className="commercial-relations__muted">{status === 'none' ? 'Sem relação' : 'Sem acesso'}</span>;
}

/** Relações da pessoa por empresa, uma linha por empresa, só com o que o leitor pode ver. */
export function PersonRelationsTable({ relations }: { relations: PersonRelation[] }) {
  const showCustomer = relations.some((relation) => relation.customer !== 'restricted');
  const showSupplier = relations.some((relation) => relation.supplier !== 'restricted');
  return (
    <div className="commercial-relations__scroll">
      <Table hover headerTone="primary" className="commercial-relations__table">
        <caption className="commercial-relations__caption">Relações comerciais por empresa</caption>
        <thead>
          <tr>
            <th scope="col">Empresa</th>
            {showCustomer && <th scope="col">Cliente</th>}
            {showSupplier && <th scope="col">Fornecedor</th>}
          </tr>
        </thead>
        <tbody>
          {relations.map((relation) => (
            <tr key={relation.company_id}>
              <th scope="row">
                {relation.company_name}
                {!relation.company_active && <> <StatusLabel active={false} /></>}
              </th>
              {showCustomer && <td><RelationStatusLabel status={relation.customer} /></td>}
              {showSupplier && <td><RelationStatusLabel status={relation.supplier} /></td>}
            </tr>
          ))}
        </tbody>
      </Table>
    </div>
  );
}

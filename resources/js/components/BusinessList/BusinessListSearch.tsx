import InputGroup from '@/components/InputGroup/InputGroup';
import TextInput from '@/components/TextInput/TextInput';

/** Search field of the business list filter bar. Enter and the magnifier run the search immediately. */
export default function BusinessListSearch({ label, placeholder, value, onChange, onSubmit, maxLength = 255, tour }: {
  label: string; placeholder: string; value: string; onChange: (value: string) => void;
  onSubmit: () => void; maxLength?: number; tour?: string;
}) {
  return (
    <form className="business-list__search" role="search" data-tour={tour} noValidate
      onSubmit={(event) => { event.preventDefault(); onSubmit(); }}>
      <InputGroup action={
        <button className="inputGroup__button waves-effect" type="submit" aria-label="Buscar" title="Buscar">
          <i className="fa fa-search" aria-hidden="true" />
        </button>
      }>
        <TextInput type="search" aria-label={label} placeholder={placeholder} value={value} maxLength={maxLength}
          onChange={(event) => onChange(event.target.value)} />
      </InputGroup>
    </form>
  );
}

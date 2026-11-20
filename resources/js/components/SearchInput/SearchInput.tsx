import TextInput from "@/components/TextInput/TextInput";
import Button, { type ButtonTone } from "@/components/Button/Button";
import "./SearchInput.css";
export default function SearchInput({
  placeholder = "Buscar...",
  onSearch,
  value,
  onChange,
  label = placeholder,
  busy = false,
  buttonTone = 'default',
}: {
  placeholder?: string;
  onSearch?: (query: string) => void;
  value?: string;
  onChange?: (query: string) => void;
  label?: string;
  busy?: boolean;
  buttonTone?: ButtonTone;
}): JSX.Element {
  return (
    <form
      className="searchInput"
      role="search"
      aria-busy={busy}
      onSubmit={(event) => {
        event.preventDefault();
        const data = new FormData(event.currentTarget);
        onSearch?.(String(data.get("query") ?? ""));
      }}
    >
      <TextInput
        name="query"
        placeholder={placeholder}
        aria-label={label}
        type="search"
        maxLength={255}
        value={value}
        onChange={(event) => onChange?.(event.target.value)}
      />
      <Button
        type="submit"
        disabled={busy}
        tone={buttonTone}
        size="lg"
        leftIconClass="fa fa-search"
        label="Buscar"
      />
    </form>
  );
}

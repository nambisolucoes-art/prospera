import ProductGeneralForm from "@/components/ProductGeneralForm/ProductGeneralForm";
import ProductMetadataForm from "@/components/ProductMetadataForm/ProductMetadataForm";
import ProductImagesForm from "@/components/ProductImagesForm/ProductImagesForm";
import Button from "@/components/Button/Button";
import "./ProductEditor.css";
export default function ProductEditor(): JSX.Element {
  return (
    <form
      className="productEditor"
      onSubmit={(event) => event.preventDefault()}
    >
      <div className="productEditor__columns">
        <div>
          <ProductGeneralForm />
        </div>
        <div>
          <ProductMetadataForm />
          <ProductImagesForm />
        </div>
      </div>
      <hr />
      <div className="productEditor__actions">
        <Button tone="white" width="sm" label="Cancelar" />{" "}
        <Button tone="default" width="sm" label="Salvar" />{" "}
        <Button tone="danger" width="sm" label="Excluir" />
      </div>
    </form>
  );
}

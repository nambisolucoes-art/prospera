import { Fragment, useId } from "react";
import ImageDisplay from "@/components/ImageDisplay/ImageDisplay";
import ChoiceControl from "@/components/ChoiceControl/ChoiceControl";
import RecordActions from "@/components/RecordActions/RecordActions";
import FileUploadButton from "@/components/FileUploadButton/FileUploadButton";
import ProductFormSection from "@/components/ProductFormSection/ProductFormSection";
import { productImages, imageRoles } from "./ProductImagesForm.data";
import "./ProductImagesForm.css";
export default function ProductImagesForm(): JSX.Element {
  const id = useId();
  return (
    <ProductFormSection title="Imagem do produto" images>
      {productImages.map(({ name: image, initialRole }) => (
        <div className="productImagesForm__row" key={image}>
          <div>
            <ImageDisplay
              src={`/assets/images/products/${image}.jpg`}
              alt={image}
              size="md"
            />
          </div>
          <div>
            {imageRoles.map((role, index) => (
              <Fragment key={role}>
                {index > 0 ? " " : null}
                <ChoiceControl
                  type="radio"
                  inline
                  id={`${id}-${image}-${index}`}
                  name={`${id}-${image}`}
                  label={role}
                  checked={role === initialRole}
                />
              </Fragment>
            ))}
          </div>
          <div className="productImagesForm__actions">
            <RecordActions name={`imagem ${image}`} />
          </div>
        </div>
      ))}
      <div className="productImagesForm__upload">
        <FileUploadButton label="Enviar" />
      </div>
    </ProductFormSection>
  );
}

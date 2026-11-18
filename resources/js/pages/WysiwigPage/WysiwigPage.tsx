import CardBox from '@/components/CardBox/CardBox';
import FormPage from '@/components/FormPage/FormPage';
import TinyMceEditor from '@/components/TinyMceEditor/TinyMceEditor';
import { wysiwigStyleFormats } from './WysiwigPage.data';

export default function WysiwigPage(): JSX.Element {
  return <FormPage title="Editor WYSIWYG">
    <CardBox><TinyMceEditor label="Editor WYSIWYG" styleFormats={wysiwigStyleFormats} /></CardBox>
  </FormPage>;
}

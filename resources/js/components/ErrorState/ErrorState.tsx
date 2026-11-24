import Button from '@/components/Button/Button';
import { errorDescriptions, errorStateCopy as copy } from './ErrorState.data';
import type { ErrorAction, ErrorCode, ErrorDescription } from './ErrorState.types';
import './ErrorState.css';

export default function ErrorState({ code, description = errorDescriptions[code], homeHref = 'index.html', actions }: {
  code: ErrorCode;
  description?: ErrorDescription;
  homeHref?: string;
  /** Ações da tela, da principal para as secundárias. Sem elas, só o botão de voltar ao início. */
  actions?: ErrorAction[];
}): JSX.Element {
  const buttons = actions ?? [{ label: copy.home, href: homeHref }];

  return <section className="errorState" aria-label={`${copy.label} ${code}`}>
    <div className="errorState__code" aria-hidden="true">
      <span className="errorState__primary">{code[0]}</span>
      {code[1] === '0' && code !== '400' ? <i className="ti-face-sad errorState__pink" aria-hidden="true" /> : <span className="errorState__pink">{code[1]}</span>}
      {code === '500' ? <i className="ti-face-sad errorState__info" aria-hidden="true" /> : <span className="errorState__info">{code[2]}</span>}
    </div>
    <h1>{description.title}</h1><br />
    {description.paragraphs.map((text) => <p key={text}>{text}</p>)}
    <br />
    <div className="errorState__actions">
      {buttons.map((action, index) => action.href
        ? <Button key={action.label} href={action.href} label={action.label} tone={index === 0 ? 'primary' : 'white'} />
        : <Button key={action.label} type="button" onClick={action.onClick} label={action.label} tone={index === 0 ? 'primary' : 'white'} />)}
    </div>
  </section>;
}

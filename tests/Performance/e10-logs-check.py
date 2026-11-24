"""Procura segredos sintéticos, inteiros ou parciais, nos logs. Imprime só contagens, nunca o segredo."""
import re
import sys

window = 12
secrets = [s.strip() for s in open(sys.argv[1]) if len(s.strip()) >= window]
print(f'segredos sintéticos conferidos: {len(secrets)} (janela mínima de {window} caracteres)')
for path in sys.argv[2:]:
    text = open(path, errors='replace').read()
    full = sum(1 for s in secrets if s in text)
    partial = 0
    longest = 0
    for s in secrets:
        found = 0
        for i in range(len(s) - window + 1):
            if s[i:i + window] in text:
                found = max(found, window)
                # Estende para medir o maior trecho exposto.
                j = i + window
                while j < len(s) and s[i:j + 1] in text:
                    j += 1
                found = max(found, j - i)
        if found:
            partial += 1
            longest = max(longest, found)
    lines = text.splitlines()
    statuses = len(re.findall(r'^\s+(\d{3})\s+(GET|POST|PATCH|PUT|DELETE)\s', text, re.M))
    raw = len(re.findall(r'"msg":"handled request"', text))
    print(f'{path.rsplit("/", 1)[-1]}: linhas={len(lines)} segredos inteiros={full} segredos com trecho >= {window}={partial} '
          f'maior trecho={longest} linhas de acesso (console)={statuses} linhas de acesso (JSON)={raw}')

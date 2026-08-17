import argparse
import pyexcel as p
from pathlib import Path
import sys

parser = argparse.ArgumentParser()
parser.add_argument("--file", required=True)
args = parser.parse_args()

xls_path = Path(args.file)
xlsx_path = xls_path.with_suffix(".xlsx")

try:
    print(f"Convertendo {xls_path} -> {xlsx_path}")
    p.save_book_as(file_name=str(xls_path), dest_file_name=str(xlsx_path))

    if xlsx_path.exists():
        # Remover o .xls original
        xls_path.unlink()
        print(f"Conversão concluída. Arquivo .xls removido: {xls_path}")
        sys.exit(0)
    else:
        print("Falha: .xlsx não foi gerado.")
        sys.exit(1)

except Exception as e:
    print(f"Erro na conversão: {e}")
    sys.exit(1)

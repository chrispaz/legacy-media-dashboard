#!/usr/bin/env python3
# import-recipes.py — Importa recetas en bloque desde un archivo JSON
#
# Uso:
#   python3 tools/import-recipes.py                        # usa tools/recipes.json
#   python3 tools/import-recipes.py mis-recetas.json       # archivo personalizado
#   python3 tools/import-recipes.py recipes.json --dry-run # simula sin subir
#
# Formato del JSON:
#   Array de objetos con campos:
#     name        (string, obligatorio)
#     ingredients (array de {qty, name})
#     steps       (array de strings)
#     comments    (string, opcional)

import json
import sys
import os
import urllib.request
import urllib.error

# ── Configuración ─────────────────────────────────────────────────────────────
API_URL  = "https://legacy-media-dashboard.vercel.app/api/recipes"
# Para probar en local:
# API_URL = "http://localhost:3333/api/recipes"

# ── Helpers ───────────────────────────────────────────────────────────────────
def normalize(recipe):
    """Convierte el formato simple del JSON al formato que espera la API."""
    ingredients = []
    for ing in recipe.get("ingredients", []):
        if isinstance(ing, dict):
            ingredients.append({
                "qty":     str(ing.get("qty", "")).strip(),
                "name":    str(ing.get("name", "")).strip(),
                "checked": False
            })

    steps = []
    for s in recipe.get("steps", []):
        text = ""
        if isinstance(s, str):
            text = s.strip()
        elif isinstance(s, dict):
            text = str(s.get("text", "")).strip()
        if text:
            steps.append({"text": text, "checked": False})

    return {
        "name":        str(recipe.get("name", "Sin nombre")).strip(),
        "ingredients": ingredients,
        "steps":       steps,
        "comments":    str(recipe.get("comments", "")).strip()
    }

def post_recipe(payload):
    """Envía una receta a la API. Retorna el id creado o lanza excepción."""
    data = json.dumps(payload).encode("utf-8")
    req  = urllib.request.Request(
        API_URL,
        data    = data,
        headers = {"Content-Type": "application/json"},
        method  = "POST"
    )
    with urllib.request.urlopen(req, timeout=15) as resp:
        return json.loads(resp.read().decode("utf-8"))

# ── Main ──────────────────────────────────────────────────────────────────────
def main():
    # Argumentos
    args    = sys.argv[1:]
    dry_run = "--dry-run" in args
    args    = [a for a in args if not a.startswith("--")]

    script_dir = os.path.dirname(os.path.abspath(__file__))
    filename   = args[0] if args else os.path.join(script_dir, "recipes.json")

    if not os.path.exists(filename):
        print(f"Error: no se encontro el archivo '{filename}'")
        sys.exit(1)

    with open(filename, encoding="utf-8") as f:
        try:
            recipes = json.load(f)
        except json.JSONDecodeError as e:
            print(f"Error de JSON en '{filename}': {e}")
            sys.exit(1)

    if not isinstance(recipes, list):
        print("Error: el archivo debe contener un array JSON [ {...}, {...} ]")
        sys.exit(1)

    total  = len(recipes)
    ok     = 0
    errors = 0

    print(f"\nLMD — Importador de recetas")
    print(f"API: {API_URL}")
    print(f"Archivo: {filename}")
    print(f"Recetas: {total}")
    if dry_run:
        print("MODO: dry-run (no se sube nada)\n")
    print("-" * 50)

    for i, raw in enumerate(recipes):
        payload = normalize(raw)
        name    = payload["name"]

        n_ing   = len(payload["ingredients"])
        n_steps = len(payload["steps"])
        preview = f"{name}  ({n_ing} ingredientes, {n_steps} pasos)"

        if dry_run:
            print(f"  [DRY] {preview}")
            ok += 1
            continue

        try:
            result = post_recipe(payload)
            rid    = result.get("id", "?")
            print(f"  OK  {preview}  -> id {rid}")
            ok += 1
        except urllib.error.HTTPError as e:
            body = ""
            try:
                body = e.read().decode("utf-8")
            except Exception:
                pass
            print(f"  ERR {preview}  -> HTTP {e.code}  {body}")
            errors += 1
        except Exception as e:
            print(f"  ERR {preview}  -> {e}")
            errors += 1

    print("-" * 50)
    print(f"Resultado: {ok} importadas, {errors} errores.\n")

if __name__ == "__main__":
    main()

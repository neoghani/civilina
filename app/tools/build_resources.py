"""Builds the three free Excel templates of civilina.com/ressources/ (milestone M4).

    py -3 app/tools/build_resources.py

Writes app/private/resources/civilina-matrice-raci.xlsx, civilina-modele-itp.xlsx
and civilina-fiche-kpi.xlsx. Yellow cells are the ones to fill in; each template
has an instructions sheet and a filled example. Formulas are computed by Excel.
"""

from pathlib import Path

from openpyxl import Workbook
from openpyxl.chart import LineChart, Reference
from openpyxl.formatting.rule import CellIsRule, FormulaRule
from openpyxl.styles import Alignment, Border, Font, PatternFill, Side
from openpyxl.utils import get_column_letter
from openpyxl.worksheet.datavalidation import DataValidation

OUT = Path(__file__).resolve().parents[1] / "private" / "resources"
OUT.mkdir(parents=True, exist_ok=True)

NAVY = "102033"
BLUE = "003B8C"
FONT = "Arial"
INPUT = PatternFill("solid", fgColor="FFF4C2")  # cells to fill in
HEAD = PatternFill("solid", fgColor=NAVY)
SUB = PatternFill("solid", fgColor="E8EFF9")
THIN = Side(style="thin", color="C9D3DF")
BOX = Border(left=THIN, right=THIN, top=THIN, bottom=THIN)
WRAP = Alignment(wrap_text=True, vertical="top")
CENTER = Alignment(horizontal="center", vertical="center", wrap_text=True)


def font(size=10, bold=False, color="252A31", italic=False):
    return Font(name=FONT, size=size, bold=bold, color=color, italic=italic)


def title_block(ws, title, subtitle, width_cols):
    ws["A1"] = title
    ws["A1"].font = font(16, True, BLUE)
    ws["A2"] = subtitle
    ws["A2"].font = font(10, False, "5D6673", True)
    ws.merge_cells(start_row=1, start_column=1, end_row=1, end_column=width_cols)
    ws.merge_cells(start_row=2, start_column=1, end_row=2, end_column=width_cols)
    ws.row_dimensions[1].height = 26


def header_row(ws, row, labels):
    for i, label in enumerate(labels, start=1):
        c = ws.cell(row=row, column=i, value=label)
        c.font = font(10, True, "FFFFFF")
        c.fill = HEAD
        c.alignment = CENTER
        c.border = BOX
    ws.row_dimensions[row].height = 42


def instructions(wb, title, lines, source):
    ws = wb.create_sheet("Mode d'emploi", 0)
    ws.sheet_view.showGridLines = False
    ws.column_dimensions["A"].width = 3
    ws.column_dimensions["B"].width = 110
    ws["B2"] = title
    ws["B2"].font = font(18, True, BLUE)
    r = 4
    for kind, text in lines:
        cell = ws.cell(row=r, column=2, value=text)
        if kind == "h":
            cell.font = font(12, True, NAVY)
            r += 1
        else:
            cell.font = font(10)
            cell.alignment = WRAP
        r += 1
    r += 1
    ws.cell(row=r, column=2, value="Cellules jaunes = à remplir. Les autres cellules sont calculées ou servent de repère.").font = font(10, True)
    ws.cell(row=r, column=2).fill = INPUT
    r += 2
    ws.cell(row=r, column=2, value=source).font = font(9, False, "5D6673", True)
    ws.cell(row=r + 1, column=2, value="Modèle Civilina — civilina.com. Libre d'usage interne ; à adapter aux exigences de votre marché et de vos documents contractuels.").font = font(9, False, "5D6673", True)
    return ws


def finish(ws, landscape=True):
    ws.page_setup.orientation = "landscape" if landscape else "portrait"
    ws.page_setup.fitToWidth = 1
    ws.page_setup.fitToHeight = 0
    ws.sheet_properties.pageSetUpPr.fitToPage = True
    ws.print_options.horizontalCentered = True


# ---------------------------------------------------------------- RACI
def build_raci():
    wb = Workbook()
    ws = wb.active
    ws.title = "Matrice RACI"
    roles = ["Maître d'ouvrage", "Direction de projet", "Chef de projet", "Bureau d'études", "Responsable qualité", "Entreprise travaux", "Contrôle technique", "Achats"]
    cols = ["N°", "Phase", "Activité ou livrable"] + roles + ["Nb A", "Nb R", "Contrôle"]
    last_col = len(cols)
    title_block(ws, "Matrice RACI — projet de construction", "R = Réalise · A = Approuve (un seul par ligne) · C = Consulté · I = Informé", last_col)
    ws["A3"] = "Projet :"
    ws["A3"].font = font(10, True)
    ws["C3"] = "Bâtiment de bureaux R+4 (exemple)"
    ws["C3"].fill = INPUT
    ws["C3"].font = font(10)
    hr = 5
    header_row(ws, hr, cols)
    example = [
        ("Cadrage", "Charte du projet et objectifs", ["A", "R", "C", "C", "C", "", "", ""]),
        ("Cadrage", "Plan de management de projet", ["I", "A", "R", "C", "C", "", "", ""]),
        ("Études", "Note d'hypothèses et dimensionnement", ["I", "", "A", "R", "", "", "C", ""]),
        ("Études", "Plans d'exécution (visa)", ["I", "", "A", "R", "C", "C", "C", ""]),
        ("Qualité", "Plan d'Assurance Qualité (PAQ)", ["I", "A", "C", "C", "R", "C", "I", ""]),
        ("Qualité", "Plans d'inspection et d'essais (ITP)", ["", "", "A", "C", "R", "R", "C", ""]),
        ("Achats", "Consultation et choix des sous-traitants", ["I", "A", "C", "C", "C", "", "", "R"]),
        ("Travaux", "Planning détaillé d'exécution", ["I", "", "A", "C", "", "R", "", "C"]),
        ("Travaux", "Réception des supports (points d'arrêt)", ["", "", "A", "", "C", "R", "C", ""]),
        ("Qualité", "Traitement des non-conformités", ["I", "", "A", "C", "R", "R", "I", ""]),
        ("Clôture", "Opérations préalables à la réception", ["C", "", "A", "C", "C", "R", "C", ""]),
        ("Clôture", "Dossier des ouvrages exécutés (DOE)", ["I", "", "A", "C", "C", "R", "", ""]),
    ]
    first = hr + 1
    last = hr + 40
    for i in range(first, last + 1):
        idx = i - first
        n = ws.cell(row=i, column=1, value=idx + 1)
        n.alignment = CENTER
        if idx < len(example):
            phase, act, codes = example[idx]
            ws.cell(row=i, column=2, value=phase)
            ws.cell(row=i, column=3, value=act)
            for j, code in enumerate(codes):
                ws.cell(row=i, column=4 + j, value=code or None)
        for j in range(2, 4 + len(roles)):
            c = ws.cell(row=i, column=j)
            c.fill = INPUT
            c.border = BOX
            c.font = font(10)
            c.alignment = CENTER if j >= 4 else WRAP
        grid = f"D{i}:{get_column_letter(3 + len(roles))}{i}"
        a_col = get_column_letter(4 + len(roles))
        r_col = get_column_letter(5 + len(roles))
        k_col = get_column_letter(6 + len(roles))
        ws[f"{a_col}{i}"] = f'=COUNTIF({grid},"A")'
        ws[f"{r_col}{i}"] = f'=COUNTIF({grid},"R")'
        ws[f"{k_col}{i}"] = f'=IF(C{i}="","",IF({a_col}{i}<>1,"Un seul A requis",IF({r_col}{i}<1,"Au moins un R","OK")))'
        for col in (a_col, r_col, k_col):
            ws[f"{col}{i}"].alignment = CENTER
            ws[f"{col}{i}"].border = BOX
            ws[f"{col}{i}"].font = font(10)
        n.border = BOX
        n.font = font(10)
    # Workload per role
    tot = last + 2
    ws.cell(row=tot, column=3, value="Nombre de R par rôle").font = font(10, True)
    ws.cell(row=tot + 1, column=3, value="Nombre de A par rôle").font = font(10, True)
    for j in range(len(roles)):
        col = get_column_letter(4 + j)
        for k, code in enumerate(("R", "A")):
            c = ws[f"{col}{tot + k}"]
            c.value = f'=COUNTIF({col}{first}:{col}{last},"{code}")'
            c.alignment = CENTER
            c.font = font(10, True)
            c.fill = SUB
            c.border = BOX
    ws.cell(row=tot + 3, column=3, value="Lecture par colonne : un rôle avec beaucoup de A concentre les décisions ; un rôle sans aucune lettre n'a peut-être pas sa place dans le projet.").font = font(9, False, "5D6673", True)
    # Lists and colours
    dv = DataValidation(type="list", formula1='"R,A,C,I"', allow_blank=True, showErrorMessage=True, errorTitle="Valeur non prévue", error="Saisissez R, A, C ou I (ou laissez vide).")
    ws.add_data_validation(dv)
    dv.add(f"D{first}:{get_column_letter(3 + len(roles))}{last}")
    grid_all = f"D{first}:{get_column_letter(3 + len(roles))}{last}"
    for code, color in (("R", "CFE3FF"), ("A", "FFD9A8"), ("C", "D9F2E3"), ("I", "EEEEEE")):
        ws.conditional_formatting.add(grid_all, CellIsRule(operator="equal", formula=[f'"{code}"'], fill=PatternFill("solid", fgColor=color), font=Font(name=FONT, bold=True)))
    k_range = f"{get_column_letter(6 + len(roles))}{first}:{get_column_letter(6 + len(roles))}{last}"
    ws.conditional_formatting.add(k_range, CellIsRule(operator="equal", formula=['"OK"'], font=Font(name=FONT, color="16845B", bold=True)))
    ws.conditional_formatting.add(k_range, FormulaRule(formula=[f'AND({get_column_letter(6 + len(roles))}{first}<>"",{get_column_letter(6 + len(roles))}{first}<>"OK")'], font=Font(name=FONT, color="B23A3A", bold=True)))
    widths = [5, 12, 40] + [13] * len(roles) + [7, 7, 18]
    for i, w in enumerate(widths, start=1):
        ws.column_dimensions[get_column_letter(i)].width = w
    ws.freeze_panes = ws[f"D{first}"]
    finish(ws)
    instructions(wb, "Matrice RACI — mode d'emploi", [
        ("h", "À quoi sert la matrice"),
        ("p", "Elle dit, pour chaque activité ou livrable, qui réalise, qui approuve, qui est consulté et qui est informé. Elle supprime les zones grises : chaque décision a un propriétaire, chaque tâche un exécutant."),
        ("h", "Les quatre lettres"),
        ("p", "R — Réalise : fait le travail. Il peut y avoir plusieurs R sur une ligne."),
        ("p", "A — Approuve : rend des comptes et valide le résultat. Un seul A par ligne, toujours."),
        ("p", "C — Consulté : donne son avis avant la décision (échange dans les deux sens)."),
        ("p", "I — Informé : est tenu au courant après la décision (information à sens unique)."),
        ("h", "Comment la remplir"),
        ("p", "1. Renommez les colonnes de rôles selon votre organisation (fonctions, pas des noms de personnes)."),
        ("p", "2. Listez les activités et les livrables, de préférence à partir de votre WBS."),
        ("p", "3. Choisissez les lettres dans la liste déroulante. La colonne « Contrôle » signale les lignes sans A, avec plusieurs A ou sans R."),
        ("p", "4. Lisez ensuite par colonne (en bas du tableau) : un rôle surchargé de A ou de R est un risque de goulot d'étranglement."),
        ("p", "5. Faites valider la matrice en réunion de lancement, puis tenez-la à jour à chaque changement d'organisation."),
        ("h", "L'exemple"),
        ("p", "Les 12 premières lignes montrent une matrice remplie pour un bâtiment de bureaux. Remplacez-les par vos propres activités."),
    ], "Guide complet : « La matrice RACI : modèle commenté pour clarifier qui fait quoi », civilina.com/journal/matrice-raci-modele-commente/")
    wb.save(OUT / "civilina-matrice-raci.xlsx")


# ---------------------------------------------------------------- ITP
def build_itp():
    wb = Workbook()
    ws = wb.active
    ws.title = "ITP"
    cols = ["N°", "Phase", "Activité / contrôle", "Référence (norme, spécification, plan)", "Critère d'acceptation", "Méthode de contrôle", "Fréquence", "Entreprise", "Maîtrise d'œuvre / contrôle", "Maître d'ouvrage", "Document de preuve", "Responsable", "Observations"]
    last_col = len(cols)
    title_block(ws, "Plan d'inspection et d'essais (ITP)", "H = point d'arrêt · W = point témoin · R = revue documentaire · S = surveillance — un point H bloque la suite des travaux tant qu'il n'est pas levé.", last_col)
    head = [("Projet", "Bâtiment de bureaux R+4 (exemple)"), ("Lot / ouvrage", "Gros œuvre — voiles en béton armé"), ("Référence ITP", "ITP-GO-003"), ("Révision", "B")]
    head2 = [("Établi par", "Responsable qualité entreprise"), ("Vérifié par", "Maîtrise d'œuvre"), ("Approuvé par", "Maître d'ouvrage"), ("Date", "16/11/2026")]
    for i, (label, value) in enumerate(head):
        ws.cell(row=3 + i, column=1, value=label).font = font(10, True)
        c = ws.cell(row=3 + i, column=3, value=value)
        c.fill = INPUT
        c.font = font(10)
    for i, (label, value) in enumerate(head2):
        ws.cell(row=3 + i, column=5, value=label).font = font(10, True)
        c = ws.cell(row=3 + i, column=6, value=value)
        c.fill = INPUT
        c.font = font(10)
    hr = 8
    header_row(ws, hr, cols)
    example = [
        ("Préparation", "Visa des plans d'exécution du voile", "Plans d'exécution, CCTP", "Plans visés « bon pour exécution », indice en vigueur", "Revue documentaire", "Avant chaque voile", "R", "H", "I", "Plans visés", "Conducteur de travaux"),
        ("Préparation", "Réception des matériaux (aciers)", "NF A 35-080, bons de livraison", "Nuance et diamètres conformes, certificats fournis", "Contrôle visuel + certificats", "Chaque livraison", "R", "R", "", "Bon de livraison + certificat", "Chef de chantier"),
        ("Exécution", "Implantation et coffrage", "Plans d'exécution", "Écart d'implantation ≤ tolérance du marché", "Mesure topographique", "Chaque voile", "R", "W", "", "Fiche de contrôle implantation", "Géomètre"),
        ("Exécution", "Ferraillage avant coulage", "NF EN 13670, plans de ferraillage", "Diamètres, espacements, enrobages et recouvrements conformes", "Contrôle visuel et mesure", "Chaque voile", "R", "H", "I", "PV de réception des armatures", "Chef de chantier"),
        ("Exécution", "Bétonnage", "NF EN 206, NF EN 13670", "Classe de béton commandée, affaissement conforme", "Bon de livraison + essai d'affaissement", "Chaque toupie", "R", "W", "", "Bons de livraison, fiche de bétonnage", "Chef de chantier"),
        ("Essais", "Prélèvements pour essais de compression", "NF EN 12390 (essais), CCTP", "Résistance caractéristique atteinte à 28 jours", "Éprouvettes en laboratoire", "Selon fréquence du CCTP", "R", "R", "I", "Rapports d'essais", "Responsable qualité"),
        ("Contrôle final", "Décoffrage et aspect du parement", "CCTP (classe de parement)", "Aspect conforme à la classe demandée, pas de nid de cailloux", "Contrôle visuel", "Chaque voile", "R", "W", "", "Fiche de réception du voile", "Conducteur de travaux"),
        ("Contrôle final", "Levée des réserves et PV", "Procédure qualité du projet", "Toutes les réserves levées et tracées", "Revue documentaire", "Fin de lot", "R", "H", "W", "PV de réception de l'ouvrage", "Responsable qualité"),
    ]
    first = hr + 1
    last = hr + 40
    for i in range(first, last + 1):
        idx = i - first
        n = ws.cell(row=i, column=1, value=idx + 1)
        n.alignment = CENTER
        n.border = BOX
        n.font = font(10)
        if idx < len(example):
            for j, v in enumerate(example[idx]):
                ws.cell(row=i, column=2 + j, value=v or None)
        for j in range(2, last_col + 1):
            c = ws.cell(row=i, column=j)
            c.fill = INPUT
            c.border = BOX
            c.font = font(10)
            c.alignment = CENTER if 8 <= j <= 10 else WRAP
    dv = DataValidation(type="list", formula1='"H,W,R,S"', allow_blank=True, showErrorMessage=True, errorTitle="Valeur non prévue", error="Saisissez H, W, R ou S (ou laissez vide).")
    ws.add_data_validation(dv)
    dv.add(f"H{first}:J{last}")
    party = f"H{first}:J{last}"
    for code, color in (("H", "F8C9C9"), ("W", "FFE3A8"), ("R", "CFE3FF"), ("S", "E2E2E2")):
        ws.conditional_formatting.add(party, CellIsRule(operator="equal", formula=[f'"{code}"'], fill=PatternFill("solid", fgColor=color), font=Font(name=FONT, bold=True)))
    s = last + 2
    ws.cell(row=s, column=3, value="Synthèse").font = font(11, True, NAVY)
    labels = [("Points d'arrêt (H)", "H"), ("Points témoins (W)", "W"), ("Revues documentaires (R)", "R"), ("Surveillances (S)", "S")]
    ws.cell(row=s + 1, column=8, value="Entreprise").font = font(9, True)
    ws.cell(row=s + 1, column=9, value="MOE / contrôle").font = font(9, True)
    ws.cell(row=s + 1, column=10, value="Maître d'ouvrage").font = font(9, True)
    for k, (label, code) in enumerate(labels):
        row = s + 2 + k
        ws.cell(row=row, column=3, value=label).font = font(10, True)
        for col in ("H", "I", "J"):
            c = ws[f"{col}{row}"]
            c.value = f'=COUNTIF({col}{first}:{col}{last},"{code}")'
            c.alignment = CENTER
            c.fill = SUB
            c.border = BOX
            c.font = font(10, True)
    widths = [5, 13, 32, 26, 30, 22, 16, 11, 13, 12, 26, 18, 24]
    for i, w in enumerate(widths, start=1):
        ws.column_dimensions[get_column_letter(i)].width = w
    ws.freeze_panes = ws[f"D{first}"]
    finish(ws)
    instructions(wb, "Plan d'inspection et d'essais (ITP) — mode d'emploi", [
        ("h", "À quoi sert un ITP"),
        ("p", "Il fixe, avant les travaux, tous les contrôles d'un ouvrage : quoi contrôler, selon quelle référence, avec quel critère d'acceptation, qui intervient et quelle preuve doit rester. C'est le contrat de contrôle entre l'entreprise, la maîtrise d'œuvre et le maître d'ouvrage."),
        ("h", "Les types de points"),
        ("p", "H — Point d'arrêt : les travaux s'arrêtent tant que l'intervenant n'a pas contrôlé et libéré l'étape (exemple : ferraillage avant coulage)."),
        ("p", "W — Point témoin : l'intervenant est convoqué ; s'il ne vient pas dans le délai prévu, les travaux continuent et le contrôle est tracé."),
        ("p", "R — Revue documentaire : contrôle sur pièces (plans, certificats, rapports)."),
        ("p", "S — Surveillance : contrôle par sondage, sans convocation."),
        ("h", "Comment le remplir"),
        ("p", "1. Un ITP par ouvrage ou par lot. Renseignez l'en-tête (projet, lot, référence, révision, circuit de validation)."),
        ("p", "2. Déroulez les activités dans l'ordre d'exécution : préparation, exécution, essais, contrôle final."),
        ("p", "3. Pour chaque contrôle : la référence exacte (norme et édition, article du CCTP, plan et indice), un critère d'acceptation mesurable et le document qui en fera la preuve."),
        ("p", "4. Choisissez H, W, R ou S pour chaque intervenant. La synthèse en bas compte les points par intervenant pour planifier les convocations."),
        ("p", "5. Faites approuver l'ITP avant le démarrage du lot ; toute modification donne lieu à une nouvelle révision."),
        ("h", "L'exemple"),
        ("p", "Les 8 premières lignes portent sur des voiles en béton armé. Les références citées (NF EN 13670, NF EN 206, NF EN 12390) sont des exemples : vérifiez les normes, éditions et tolérances applicables à votre marché."),
    ], "Guide complet : « Construire un ITP : points d'arrêt, de témoin et de revue », civilina.com/journal/construire-itp-points-arret-temoin-revue/")
    wb.save(OUT / "civilina-modele-itp.xlsx")


# ---------------------------------------------------------------- KPI
def build_kpi():
    wb = Workbook()
    ws = wb.active
    ws.title = "Fiche indicateur"
    ws.sheet_view.showGridLines = False
    title_block(ws, "Fiche indicateur (KPI)", "Une fiche par indicateur : sans définition partagée, deux personnes calculeront deux chiffres différents.", 3)
    fields = [
        ("Nom de l'indicateur", "Taux de non-conformités clôturées dans le délai"),
        ("Code", "QUA-03"),
        ("Objectif servi", "Traiter les écarts qualité avant qu'ils ne bloquent la réception"),
        ("Définition", "Part des non-conformités (NCR) dont la date de clôture est inférieure ou égale à la date prévue, parmi les NCR dont l'échéance tombe dans le mois."),
        ("Formule", "NCR clôturées dans le délai ÷ NCR arrivées à échéance dans le mois"),
        ("Unité", "%"),
        ("Source des données", "Registre des NCR (onglet Qualité du tableau de bord projet)"),
        ("Fréquence de mesure", "Mensuelle, le 5 du mois suivant"),
        ("Responsable de la mesure", "Responsable qualité"),
        ("Responsable de l'action", "Chef de projet"),
        ("Cible", 0.9),
        ("Seuil d'alerte", 0.75),
        ("Sens de lecture", "Plus haut = mieux"),
        ("Représentation", "Courbe mensuelle avec cible ; état vert / orange / rouge"),
        ("Revue", "Revue de projet mensuelle"),
        ("Date de création", "16/11/2026"),
        ("Révision", "A"),
    ]
    header_row(ws, 4, ["Rubrique", "Contenu", "Conseil"])
    tips = {
        "Nom de l'indicateur": "Un nom qui se comprend seul, sans sigle.",
        "Définition": "Précisez ce qui entre et ce qui n'entre pas dans le calcul.",
        "Formule": "Numérateur ÷ dénominateur, avec leurs unités.",
        "Cible": "Valeur visée sur la période (pour un %, saisir 0,9 pour 90 %).",
        "Seuil d'alerte": "En dessous (ou au-dessus selon le sens), l'état passe au rouge.",
        "Sens de lecture": "« Plus haut = mieux » ou « Plus bas = mieux ».",
        "Responsable de l'action": "Celui qui décide quand l'indicateur est au rouge.",
    }
    for i, (label, value) in enumerate(fields):
        r = 5 + i
        ws.cell(row=r, column=1, value=label).font = font(10, True, NAVY)
        c = ws.cell(row=r, column=2, value=value)
        c.fill = INPUT
        c.font = font(10)
        c.alignment = WRAP
        t = ws.cell(row=r, column=3, value=tips.get(label, ""))
        t.font = font(9, False, "5D6673", True)
        t.alignment = WRAP
        for col in (1, 2, 3):
            ws.cell(row=r, column=col).border = BOX
    cible_row = 5 + [f[0] for f in fields].index("Cible")
    seuil_row = 5 + [f[0] for f in fields].index("Seuil d'alerte")
    sens_row = 5 + [f[0] for f in fields].index("Sens de lecture")
    unit_row = 5 + [f[0] for f in fields].index("Unité")
    ws[f"B{cible_row}"].number_format = "0%"
    ws[f"B{seuil_row}"].number_format = "0%"
    dv = DataValidation(type="list", formula1='"Plus haut = mieux,Plus bas = mieux"', allow_blank=False)
    ws.add_data_validation(dv)
    dv.add(f"B{sens_row}")
    ws.column_dimensions["A"].width = 26
    ws.column_dimensions["B"].width = 62
    ws.column_dimensions["C"].width = 46
    finish(ws, landscape=False)

    sv = wb.create_sheet("Suivi mensuel")
    title_block(sv, "Suivi mensuel de l'indicateur", "Saisissez le numérateur et le dénominateur ; la valeur et l'état se calculent.", 8)
    sv["A3"] = "Indicateur :"
    sv["A3"].font = font(10, True)
    sv["B3"] = "='Fiche indicateur'!B5"
    sv["B3"].font = font(10, True, BLUE)
    hr = 5
    header_row(sv, hr, ["Mois", "Numérateur", "Dénominateur", "Valeur", "Cible", "Seuil d'alerte", "État", "Commentaire / action décidée"])
    months = ["Janv. 2026", "Févr. 2026", "Mars 2026", "Avr. 2026", "Mai 2026", "Juin 2026", "Juil. 2026", "Août 2026", "Sept. 2026", "Oct. 2026", "Nov. 2026", "Déc. 2026"]
    data = [(18, 22, "Retards liés aux essais en laboratoire"), (20, 23, ""), (15, 21, "Plan d'action : relance hebdomadaire des NCR ouvertes"), (21, 22, "")]
    cible = f"'Fiche indicateur'!$B${cible_row}"
    seuil = f"'Fiche indicateur'!$B${seuil_row}"
    sens = f"'Fiche indicateur'!$B${sens_row}"
    for i, month in enumerate(months):
        r = hr + 1 + i
        sv.cell(row=r, column=1, value=month).font = font(10, True)
        for col in (2, 3, 8):
            c = sv.cell(row=r, column=col)
            c.fill = INPUT
            c.font = font(10)
        if i < len(data):
            sv.cell(row=r, column=2, value=data[i][0])
            sv.cell(row=r, column=3, value=data[i][1])
            sv.cell(row=r, column=8, value=data[i][2] or None)
        sv[f"D{r}"] = f'=IF(OR(B{r}="",C{r}="",C{r}=0),"",B{r}/C{r})'
        sv[f"E{r}"] = f"={cible}"
        sv[f"F{r}"] = f"={seuil}"
        sv[f"G{r}"] = (f'=IF(D{r}="","",IF({sens}="Plus bas = mieux",'
                       f'IF(D{r}<=E{r},"Vert",IF(D{r}<=F{r},"Orange","Rouge")),'
                       f'IF(D{r}>=E{r},"Vert",IF(D{r}>=F{r},"Orange","Rouge"))))')
        for col in ("D", "E", "F"):
            sv[f"{col}{r}"].number_format = "0.0%"
        for col in range(1, 9):
            c = sv.cell(row=r, column=col)
            c.border = BOX
            if col in (2, 3, 4, 5, 6, 7):
                c.alignment = CENTER
        sv.cell(row=r, column=8).alignment = WRAP
    rng = f"G{hr + 1}:G{hr + 12}"
    for state, color, txt in (("Vert", "D9F2E3", "0C5A3D"), ("Orange", "FFE3A8", "8A5A00"), ("Rouge", "F8C9C9", "8A1F1F")):
        sv.conditional_formatting.add(rng, CellIsRule(operator="equal", formula=[f'"{state}"'], fill=PatternFill("solid", fgColor=color), font=Font(name=FONT, bold=True, color=txt)))
    tot = hr + 14
    sv.cell(row=tot, column=1, value="Cumul de l'année").font = font(10, True)
    sv[f"B{tot}"] = f"=SUM(B{hr + 1}:B{hr + 12})"
    sv[f"C{tot}"] = f"=SUM(C{hr + 1}:C{hr + 12})"
    sv[f"D{tot}"] = f'=IF(C{tot}=0,"",B{tot}/C{tot})'
    sv[f"D{tot}"].number_format = "0.0%"
    for col in ("A", "B", "C", "D"):
        sv[f"{col}{tot}"].fill = SUB
        sv[f"{col}{tot}"].border = BOX
        sv[f"{col}{tot}"].font = font(10, True)
    for col, w in zip("ABCDEFGH", (13, 13, 14, 11, 10, 13, 10, 48)):
        sv.column_dimensions[col].width = w
    sv.freeze_panes = sv[f"B{hr + 1}"]
    chart = LineChart()
    chart.title = "Valeur mensuelle et cible"
    chart.y_axis.title = "Taux"
    chart.y_axis.number_format = "0%"
    chart.y_axis.scaling.min = 0
    chart.y_axis.scaling.max = 1
    chart.height = 7.5
    chart.width = 18
    chart.add_data(Reference(sv, min_col=4, min_row=hr, max_row=hr + 12), titles_from_data=True)
    chart.add_data(Reference(sv, min_col=5, min_row=hr, max_row=hr + 12), titles_from_data=True)
    chart.set_categories(Reference(sv, min_col=1, min_row=hr + 1, max_row=hr + 12))
    chart.roundedCorners = False
    chart.legend.overlay = False
    chart.title.overlay = False
    chart.y_axis.title.overlay = False
    chart.x_axis.delete = False
    chart.y_axis.delete = False
    chart.series[0].graphicalProperties.line.solidFill = BLUE
    chart.series[1].graphicalProperties.line.solidFill = "16845B"
    chart.series[1].graphicalProperties.line.dashStyle = "dash"
    sv.add_chart(chart, f"A{tot + 2}")
    finish(sv)
    instructions(wb, "Fiche indicateur (KPI) — mode d'emploi", [
        ("h", "À quoi sert la fiche"),
        ("p", "Elle fixe une fois pour toutes ce que mesure un indicateur, comment on le calcule, d'où viennent les données, qui le mesure et qui agit quand il dérive. C'est elle qui rend un tableau de bord fiable."),
        ("h", "Comment l'utiliser"),
        ("p", "1. Onglet « Fiche indicateur » : remplissez chaque rubrique. La cible et le seuil d'alerte se saisissent en valeur (0,9 = 90 %)."),
        ("p", "2. Choisissez le sens de lecture : « Plus haut = mieux » (taux de conformité) ou « Plus bas = mieux » (nombre d'accidents, retard)."),
        ("p", "3. Onglet « Suivi mensuel » : saisissez chaque mois le numérateur et le dénominateur. La valeur, l'état (vert, orange, rouge) et la courbe se mettent à jour."),
        ("p", "4. Notez dans la dernière colonne l'action décidée quand l'état est orange ou rouge : c'est ce qui transforme la mesure en pilotage."),
        ("p", "5. Dupliquez le fichier pour chaque indicateur ; gardez 5 à 8 indicateurs par tableau de bord, pas plus."),
        ("h", "L'exemple"),
        ("p", "La fiche et les quatre premiers mois portent sur le taux de non-conformités clôturées dans le délai. Remplacez-les par votre propre indicateur."),
    ], "Guide complet : « La fiche indicateur : définir un KPI qui sert vraiment à piloter », civilina.com/journal/fiche-indicateur-kpi-modele-commente/")
    wb.save(OUT / "civilina-fiche-kpi.xlsx")


if __name__ == "__main__":
    build_raci()
    build_itp()
    build_kpi()
    for f in sorted(OUT.glob("*.xlsx")):
        print(f.name, f.stat().st_size)

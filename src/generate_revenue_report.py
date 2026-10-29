from reportlab.lib import colors
from reportlab.lib.enums import TA_CENTER
from reportlab.lib.pagesizes import A4, landscape
from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
from reportlab.lib.units import mm
from reportlab.platypus import (
    SimpleDocTemplate, Paragraph, Spacer, Table, TableStyle, PageBreak,
)


OUT = "/home/derrick/pythonApp/files/code/revenue_analysis_04-10_october_2026.pdf"

daily = [
    ("04 Oct", 7_266_536, None, 47_901, 207_831, 2.86, 7_058_705, 97.14),
    ("05 Oct", 7_072_766, -2.67, 46_576, 176_110, 2.49, 6_896_656, 97.51),
    ("06 Oct", 7_060_637, -0.17, 46_516, 214_019, 3.03, 6_846_618, 96.97),
    ("07 Oct", 6_990_310, -1.00, 46_049, 199_566, 2.85, 6_790_744, 97.15),
    ("08 Oct", 7_218_719, 3.27, 47_146, 224_901, 3.12, 6_993_818, 96.88),
    ("09 Oct", 7_750_088, 7.36, 50_325, 184_626, 2.38, 7_565_462, 97.62),
    ("10 Oct", 7_692_608, -0.74, 50_138, 190_640, 2.48, 7_501_968, 97.52),
]

bridges = [
    ("05 Oct", 20_745, 3_415_001, 22_070, 2_961_152, -647_619, -193_770),
    ("06 Oct", 20_924, 3_416_446, 20_984, 2_801_259, -627_316, -12_129),
    ("07 Oct", 20_757, 3_391_733, 21_224, 2_822_143, -639_917, -70_327),
    ("08 Oct", 22_247, 3_641_140, 21_150, 2_828_403, -584_328, 228_409),
    ("09 Oct", 24_329, 4_042_019, 21_150, 2_854_699, -655_951, 531_369),
    ("10 Oct", 22_831, 3_785_022, 23_018, 3_150_851, -691_651, -57_480),
]

root = [
    ("05 Oct", "2,839,160 (95.88%)", "600 (0.02%)", "121,392 (4.10%)", "40,601 / 268", "479 / 5"),
    ("06 Oct", "2,693,300 (96.15%)", "750 (0.03%)", "107,209 (3.83%)", "36,107 / 241", "1,288 / 9"),
    ("07 Oct", "2,698,446 (95.62%)", "1,260 (0.04%)", "122,437 (4.34%)", "39,016 / 262", "788 / 6"),
    ("10 Oct", "3,027,453 (96.08%)", "1,550 (0.05%)", "121,848 (3.87%)", "40,133 / 264", "590 / 5"),
]


def tsh(v):
    return f"{v:,.0f}"


styles = getSampleStyleSheet()
styles.add(ParagraphStyle(name="Title2", parent=styles["Title"], fontName="Helvetica-Bold", fontSize=18, leading=21, textColor=colors.HexColor("#173A5E"), alignment=TA_CENTER, spaceAfter=6))
styles.add(ParagraphStyle(name="Sub", parent=styles["Normal"], fontSize=8.5, leading=11, textColor=colors.HexColor("#4B5563"), alignment=TA_CENTER, spaceAfter=8))
styles.add(ParagraphStyle(name="H", parent=styles["Heading2"], fontName="Helvetica-Bold", fontSize=11, leading=13, textColor=colors.HexColor("#173A5E"), spaceBefore=6, spaceAfter=5))
styles.add(ParagraphStyle(name="Body2", parent=styles["BodyText"], fontSize=8.6, leading=11, spaceAfter=4))
styles.add(ParagraphStyle(name="Small", parent=styles["BodyText"], fontSize=7.2, leading=9, textColor=colors.HexColor("#4B5563")))
styles.add(ParagraphStyle(name="Callout", parent=styles["BodyText"], fontName="Helvetica-Bold", fontSize=9.2, leading=12, textColor=colors.HexColor("#173A5E"), backColor=colors.HexColor("#EAF2F8"), borderPadding=7, spaceAfter=7))


def tbl(data, widths, aligns=None, font=7.2):
    t = Table(data, colWidths=widths, repeatRows=1, hAlign="LEFT")
    commands = [
        ("BACKGROUND", (0, 0), (-1, 0), colors.HexColor("#173A5E")),
        ("TEXTCOLOR", (0, 0), (-1, 0), colors.white),
        ("FONTNAME", (0, 0), (-1, 0), "Helvetica-Bold"),
        ("FONTNAME", (0, 1), (-1, -1), "Helvetica"),
        ("FONTSIZE", (0, 0), (-1, -1), font),
        ("LEADING", (0, 0), (-1, -1), font + 2),
        ("GRID", (0, 0), (-1, -1), 0.3, colors.HexColor("#CBD5E1")),
        ("ROWBACKGROUNDS", (0, 1), (-1, -1), [colors.white, colors.HexColor("#F6F8FA")]),
        ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
        ("TOPPADDING", (0, 0), (-1, -1), 3),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 3),
    ]
    if aligns:
        for col, align in enumerate(aligns):
            commands.append(("ALIGN", (col, 1), (col, -1), align))
            commands.append(("ALIGN", (col, 0), (col, 0), align))
    t.setStyle(TableStyle(commands))
    return t


def footer(canvas, doc):
    canvas.saveState()
    canvas.setFont("Helvetica", 7)
    canvas.setFillColor(colors.HexColor("#6B7280"))
    canvas.drawString(12 * mm, 7 * mm, "AfyaCall revenue analysis | Source: production PostgreSQL extracts")
    canvas.drawRightString(285 * mm, 7 * mm, f"Page {doc.page}")
    canvas.restoreState()


doc = SimpleDocTemplate(OUT, pagesize=landscape(A4), rightMargin=12*mm, leftMargin=12*mm, topMargin=10*mm, bottomMargin=12*mm, title="AfyaCall Revenue Analysis: 4-10 October 2026", author="OpenAI Codex")
story = []
story.append(Paragraph("AfyaCall Revenue Analysis", styles["Title2"]))
story.append(Paragraph("4–10 October 2026 | Currency: Tsh | SUCCESS transactions by payment created_at", styles["Sub"]))
story.append(Paragraph("Executive finding", styles["H"]))
story.append(Paragraph("Revenue moved from Tsh 7.27m to Tsh 7.69m (+5.86% across the window), peaking at Tsh 7.75m on 9 Oct. Existing subscribers produced 96.88%–97.62% of daily revenue. The material 8–9 Oct rise was recovery/charging of the existing base, while ~96% of prior-day revenue lost on down days was associated with insufficient balance—not churn.", styles["Callout"]))

headers = ["Date", "Revenue (Tsh)", "DoD", "Payers", "New opt-in rev", "New %", "Existing rev", "Existing %"]
rows = [headers]
for d, rev, dod, payers, nr, np, er, ep in daily:
    rows.append([d, tsh(rev), "—" if dod is None else f"{dod:+.2f}%", f"{payers:,}", tsh(nr), f"{np:.2f}%", tsh(er), f"{ep:.2f}%"])
story.append(tbl(rows, [18*mm, 29*mm, 17*mm, 20*mm, 30*mm, 18*mm, 31*mm, 20*mm], ["LEFT","RIGHT","RIGHT","RIGHT","RIGHT","RIGHT","RIGHT","RIGHT"], 7.4))
story.append(Spacer(1, 4))
story.append(Paragraph("Daily drivers", styles["H"]))
drivers = [
    "<b>04 Oct — baseline:</b> Tsh 7,266,536; P02 supplied Tsh 4.93m (67.85%), P01 Tsh 1.99m (27.45%), P03 Tsh 0.34m (4.69%).",
    "<b>05 Oct — down Tsh 193,770 (-2.67%):</b> P02 -120,207, P01 -49,237 and P03 -25,326; airtime fell Tsh 185,570 and M-Pesa Tsh 8,200. New opt-in revenue also fell Tsh 31,721, but the existing base caused Tsh 162,049 (83.63%) of the decline.",
    "<b>06 Oct — effectively flat, -Tsh 12,129 (-0.17%):</b> P02 -19,408 and P01 -8,939 were largely offset by P03 +15,218 and M-Pesa +8,000. New opt-ins rose Tsh 37,909; existing-base revenue fell Tsh 50,038.",
    "<b>07 Oct — down Tsh 70,327 (-1.00%):</b> P02 -52,729 and P01 -33,458 outweighed P03 +16,860; airtime -59,227 and M-Pesa -11,100. Existing base -55,874 plus new opt-ins -14,453.",
    "<b>08 Oct — up Tsh 228,409 (+3.27%):</b> broad plan recovery: P02 +130,363, P01 +68,777, P03 +29,269. Airtime +233,909 offset M-Pesa -5,500. Existing base supplied Tsh 203,074 (88.91%) of the increase.",
    "<b>09 Oct — up Tsh 531,369 (+7.36%):</b> P02 +321,321, P01 +187,527, P03 +22,521; airtime +537,769 offset M-Pesa -6,400. Existing-base revenue rose Tsh 571,644 while new opt-in revenue fell Tsh 40,275: the rise was recovery, not acquisition.",
    "<b>10 Oct — down Tsh 57,480 (-0.74%):</b> P02 -27,959 and P03 -23,753 led the fall; airtime -69,380, partly offset by M-Pesa +11,900. Existing base -63,494; new opt-ins +6,014.",
]
for x in drivers:
    story.append(Paragraph("• " + x, styles["Body2"]))

story.append(PageBreak())
story.append(Paragraph("Payer-flow bridge and validated loss causes", styles["Title2"]))
story.append(Paragraph("The net change equals gained-payer revenue minus lost-payer prior revenue plus the revenue change among retained payers.", styles["Sub"]))
bh = ["Day", "Gained\npayers", "Gained rev", "Lost\npayers", "Lost prior rev", "Retained-payer\nchange", "Net change"]
br = [bh] + [[d, f"{gp:,}", tsh(gr), f"{lp:,}", tsh(lr), tsh(rd), tsh(net)] for d,gp,gr,lp,lr,rd,net in bridges]
story.append(tbl(br, [20*mm, 24*mm, 30*mm, 24*mm, 31*mm, 35*mm, 28*mm], ["LEFT","RIGHT","RIGHT","RIGHT","RIGHT","RIGHT","RIGHT"], 7.5))
story.append(Spacer(1, 5))
story.append(Paragraph("Root cause of customers who paid the prior day but not the next day (down/flat days)", styles["H"]))
rh = ["Next day", "Insufficient-balance\nprior rev (% lost)", "Other failed/system\nprior rev (% lost)", "No charge attempt\nprior rev (% lost)", "MNO churn overlap\nrev / customers", "Self-unsub overlap\nrev / customers"]
rr = [rh] + [list(x) for x in root]
story.append(tbl(rr, [22*mm, 39*mm, 40*mm, 39*mm, 39*mm, 39*mm], ["LEFT","RIGHT","RIGHT","RIGHT","RIGHT","RIGHT"], 7.2))
story.append(Spacer(1, 5))
story.append(Paragraph("Interpretation", styles["H"]))
story.append(Paragraph("• <b>Charging outcome dominates:</b> insufficient balance explains 95.62%–96.15% of the lost cohort’s prior-day revenue. Other failed/system statuses explain only 0.02%–0.05%. MNO churn overlaps only ~1.27%–1.38% of lost revenue; self-unsubscription is immaterial. Churn overlaps are diagnostics and are not additive to the mutually exclusive charging-result columns.", styles["Body2"]))
story.append(Paragraph("• <b>The base rotates heavily:</b> 20.7k–24.3k payers were gained daily while 21.0k–23.0k prior-day payers disappeared. This is consistent with the P1–P8 model’s frequent retrying of recent payers: daily revenue depends on when airtime becomes available, not only on base size.", styles["Body2"]))
story.append(Paragraph("• <b>Acquisition is too small to explain swings:</b> strict same-day, same-plan opt-ins contributed only 2.38%–3.12%. On the largest rise (9 Oct), their revenue actually declined 17.91%; recovered existing subscribers generated the entire uplift and more.", styles["Body2"]))
story.append(Paragraph("• <b>Inactive/expired signal:</b> 4.13%–6.87% of lost revenue belonged to customers absent from the subscriber snapshot at extraction (overlaps churn/failure). Because subscribers is a current-state table, this is a signal—not a point-in-time causal classification. The day-specific no-attempt bucket is the reliable residual: 3.83%–4.34%.", styles["Body2"]))

story.append(Paragraph("Very short way forward", styles["H"]))
story.append(Paragraph("Prioritize insufficient-balance recovery: tune P1 retry timing using observed airtime-success hours, and monitor daily gained/lost payer revenue by P02/P01. Investigate the small 3.8%–4.3% no-attempt residual separately; acquisition changes will not materially move near-term revenue.", styles["Callout"]))
story.append(Paragraph("Method / scope note: The request contains conflicting dates. The database exposes attached billing partitions for 4–11 Oct 2026 and no September payment partitions/rows, so this report follows the stated primary period, 4–10 Oct. Revenue is SUM(amount) where status=SUCCESS. A payment is ‘new opt-in’ only when a successful same-MSISDN, same-plan opt-in occurred earlier that same day; all other revenue is existing-base. Amounts use transaction created_at dates. ‘MNO churn overlap’ is any same-day churn log; self-unsub uses unsubscription_logs. Base figures in the supplied P1–P8 image were not used as exact denominators.", styles["Small"]))

doc.build(story, onFirstPage=footer, onLaterPages=footer)
print(OUT)

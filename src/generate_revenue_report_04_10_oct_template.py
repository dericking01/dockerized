from reportlab.lib import colors
from reportlab.lib.enums import TA_CENTER
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
from reportlab.lib.units import mm
from reportlab.platypus import PageBreak, Paragraph, SimpleDocTemplate, Spacer, Table, TableStyle


OUT = "/home/derrick/pythonApp/files/reports/AfyaCall_Revenue_Analysis_10-14Oct2026.pdf"
NAVY = colors.HexColor("#173A5E")
GREEN = colors.HexColor("#14833B")
RED = colors.HexColor("#B42318")
LIGHT = colors.HexColor("#EEF1F4")
GRID = colors.HexColor("#CCD2D8")
GREY = colors.HexColor("#666666")

daily = [
    ("10 Oct", "Sat", 7_692_608, None, 51_500, 8_392_067),
    ("11 Oct", "Sun", 7_630_737, -0.80, 51_147, 8_367_584),
    ("12 Oct", "Mon", 7_396_029, -3.08, 50_056, 8_386_006),
    ("13 Oct", "Tue", 7_272_106, -1.68, 48_833, 8_395_844),
    ("14 Oct", "Wed", 7_319_378, 0.65, 49_461, 7_578_113),
]

optins = [
    ("10 Oct", 9_395, 1_555, 194_326, 7_498_282, 2.53),
    ("11 Oct", 6_432, 1_201, 153_584, 7_477_153, 2.01),
    ("12 Oct", 7_889, 1_577, 191_958, 7_204_071, 2.60),
    ("13 Oct", 7_040, 1_406, 178_038, 7_094_068, 2.45),
    ("14 Oct", 8_448, 1_830, 223_731, 7_095_647, 3.06),
]

bridge = [
    ("11 Oct", 22_424, 3_706_038, 22_804, 3_077_912, -689_997, -61_871),
    ("12 Oct", 21_432, 3_512_783, 22_389, 3_029_001, -718_490, -234_708),
    ("13 Oct", 20_976, 3_461_599, 22_221, 2_949_193, -636_329, -123_923),
    ("14 Oct", 21_807, 3_571_904, 21_188, 2_848_216, -676_416, 47_272),
]

plans = [
    ("10 Oct", 5_161_600, 2_153_697, 375_311, 7_226_758, 465_850),
    ("11 Oct", 5_134_764, 2_112_505, 380_468, 7_173_887, 456_850),
    ("12 Oct", 5_016_668, 2_018_178, 360_183, 6_944_779, 451_250),
    ("13 Oct", 4_911_013, 2_001_750, 359_343, 6_824_406, 447_700),
    ("14 Oct", 4_942_754, 1_999_653, 375_971, 6_875_128, 444_250),
]

segments = [
    ("P1", "Paid ≤30 days", 333_000, 346_416),
    ("P2", "Never paid, age ≤30d", 232_000, 111_331),
    ("P3", "Paid 31–60 days ago", 148_000, 138_153),
    ("P4", "Paid 61–90 days ago", 113_000, 106_754),
    ("P5", "Never paid, age 31–60d", 120_000, 127_655),
    ("P6", "Never paid, age 61–90d", 132_000, 132_715),
    ("P7", "Paid >90 days ago", 249_000, 359_106),
    ("P8", "Never paid, age >90d", 713_000, 807_957),
]

classified_success = [
    ("10 Oct", 48_431, 68, 13, 1_625),
    ("11 Oct", 48_388, 66, 11, 1_293),
    ("12 Oct", 47_510, 101, 6, 1_184),
    ("13 Oct", 46_625, 102, 8, 821),
    ("14 Oct", 47_391, 118, 7, 659),
]


def n(v): return f"{v:,.0f}"


styles = getSampleStyleSheet()
styles.add(ParagraphStyle(name="ReportTitle", parent=styles["Title"], fontName="Helvetica-Bold", fontSize=18, leading=21, textColor=NAVY, alignment=TA_CENTER, spaceAfter=4))
styles.add(ParagraphStyle(name="Meta", parent=styles["Normal"], fontSize=7.5, leading=9, textColor=GREY, alignment=TA_CENTER, spaceAfter=7))
styles.add(ParagraphStyle(name="H1x", parent=styles["Heading2"], fontName="Helvetica-Bold", fontSize=11.5, leading=14, textColor=NAVY, spaceBefore=7, spaceAfter=5))
styles.add(ParagraphStyle(name="Bodyx", parent=styles["BodyText"], fontSize=8.15, leading=10.3, spaceAfter=3))
styles.add(ParagraphStyle(name="Smallx", parent=styles["BodyText"], fontSize=6.8, leading=8.3, textColor=GREY, spaceAfter=2))
styles.add(ParagraphStyle(name="KLabel", parent=styles["Normal"], fontSize=6.5, leading=8, textColor=GREY))
styles.add(ParagraphStyle(name="KValue", parent=styles["Normal"], fontSize=14, leading=17, textColor=NAVY))


def table(data, widths, font=6.8, aligns=None, header=True):
    t = Table(data, colWidths=widths, repeatRows=1 if header else 0, hAlign="CENTER")
    cmd = [("GRID", (0, 0), (-1, -1), .3, GRID), ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
           ("FONTSIZE", (0, 0), (-1, -1), font), ("LEADING", (0, 0), (-1, -1), font + 1.5),
           ("TOPPADDING", (0, 0), (-1, -1), 3), ("BOTTOMPADDING", (0, 0), (-1, -1), 3),
           ("ROWBACKGROUNDS", (0, 1 if header else 0), (-1, -1), [colors.white, LIGHT])]
    if header:
        cmd += [("BACKGROUND", (0, 0), (-1, 0), NAVY), ("TEXTCOLOR", (0, 0), (-1, 0), colors.white),
                ("FONTNAME", (0, 0), (-1, 0), "Helvetica-Bold")]
    if aligns:
        for i, a in enumerate(aligns): cmd.append(("ALIGN", (i, 0), (i, -1), a))
    t.setStyle(TableStyle(cmd))
    return t


def footer(canvas, doc):
    canvas.saveState(); canvas.setStrokeColor(GRID); canvas.line(18*mm, 11*mm, 192*mm, 11*mm)
    canvas.setFont("Helvetica", 6.5); canvas.setFillColor(GREY)
    canvas.drawString(18*mm, 7*mm, "Production DB analysis | All monetary figures in Tanzanian Shillings (Tsh)")
    canvas.drawRightString(192*mm, 7*mm, f"Page {doc.page}"); canvas.restoreState()


doc = SimpleDocTemplate(OUT, pagesize=A4, leftMargin=18*mm, rightMargin=18*mm, topMargin=14*mm, bottomMargin=15*mm,
                        title="AfyaCall Revenue Driver Analysis 10-14 Oct 2026", author="OpenAI Codex")
story = [Paragraph("AfyaCall Revenue Driver Analysis", styles["ReportTitle"]),
         Paragraph("10–14 Oct 2026  |  Prepared 15 Oct 2026  |  Source: billing.icg_payments, customer.customers, subscription.* (live DB)  |  Currency: Tsh", styles["Meta"])]

kpis = [[Paragraph("5-DAY SUCCESS REVENUE", styles["KLabel"]), Paragraph("SUCCESS RATE (attempts to pay)", styles["KLabel"]), Paragraph("NEW-OPT-IN SHARE OF REVENUE", styles["KLabel"]), Paragraph("P1 SHARE OF CLASSIFIED PAYERS", styles["KLabel"])],
        [Paragraph("Tsh 37,310,858", styles["KValue"]), Paragraph("0.58% – 0.65%", styles["KValue"]), Paragraph("2.01% – 3.06%", styles["KValue"]), Paragraph("99.7%+", styles["KValue"])]]
kt = Table(kpis, colWidths=[43.5*mm]*4); kt.setStyle(TableStyle([("BACKGROUND",(0,0),(-1,-1),LIGHT),("BOX",(0,0),(-1,-1),.4,GRID),("VALIGN",(0,0),(-1,-1),"TOP"),("LEFTPADDING",(0,0),(-1,-1),6),("RIGHTPADDING",(0,0),(-1,-1),4),("TOPPADDING",(0,0),(-1,0),7),("BOTTOMPADDING",(0,1),(-1,1),7)])); story += [kt, Spacer(1,4)]
story.append(Paragraph("<b>Bottom line:</b> revenue fell for three consecutive days and ended 4.85% below 10 Oct, before a modest +0.65% recovery on 14 Oct. The 12 Oct decline (-3.08%) was the decisive move and came from the existing base. Insufficient balance explained 96.2%–96.7% of lost prior-day revenue on every down day; no gateway failure was large enough to drive the trend.", styles["Bodyx"]))
story.append(Paragraph("1. Daily Revenue & Day-over-Day Change", styles["H1x"]))
dr = [["Date","Day","SUCCESS Revenue (Tsh)","DoD %","Txns","Attempts","Success Rate"]]
for d,day,rev,dod,tx,att in daily:
    dr.append([d,day,n(rev),"baseline" if dod is None else f"{dod:+.2f}%",n(tx),n(att),f"{100*tx/att:.2f}%"])
dt = table(dr,[17*mm,14*mm,36*mm,19*mm,22*mm,30*mm,25*mm],7,["CENTER","CENTER","RIGHT","RIGHT","RIGHT","RIGHT","RIGHT"])
dt.setStyle(TableStyle([("TEXTCOLOR",(3,2),(3,4),RED),("TEXTCOLOR",(3,5),(3,5),GREEN),("FONTNAME",(3,2),(3,5),"Helvetica-Bold")]))
story += [dt, Paragraph("Average ticket remained Tsh 147–150. Revenue therefore continued to track successful payer/transaction volume closely; the attempt pool itself stayed near 8.4m except on 7 Oct.", styles["Smallx"]), Paragraph("2. What Drove Each Day", styles["H1x"])]
drivers = [
    "<b>11 Oct (Sun) — -0.80%:</b> P01 -41,192 and P02 -26,836 outweighed P03 +5,157. Airtime fell Tsh 52,871 and M-Pesa Tsh 9,000. New-opt-in revenue caused Tsh 40,742 (65.85%) of the decline; existing base fell only Tsh 21,129.",
    "<b>12 Oct (Mon) — -3.08%:</b> the sharpest decline: P02 -118,096, P01 -94,327 and P03 -20,285. Airtime fell Tsh 229,108. Existing-base revenue dropped Tsh 273,082, partly offset by new opt-ins +Tsh 38,374.",
    "<b>13 Oct (Tue) — -1.68%:</b> P02 -105,655 and P01 -16,428; airtime -120,373. Existing-base revenue fell Tsh 110,003 and new-opt-in revenue Tsh 13,920. Insufficient balance explained 96.19% of lost prior-day revenue.",
    "<b>14 Oct (Wed) — +0.65%:</b> P02 +31,741 and P03 +16,628 outweighed P01 -2,097. New-opt-in revenue rose Tsh 45,693 and supplied 96.66% of the total gain; existing-base revenue was nearly flat (+Tsh 1,579).",
]
for x in drivers: story.append(Paragraph(x, styles["Bodyx"]))

story.append(PageBreak()); story += [Paragraph("3. New Opt-Ins vs. Existing Base — Revenue Contribution", styles["H1x"])]
oh = [["Date","New Opt-Ins\n(day)","Same-Day\nPayers","Revenue: New\nOpt-Ins","Revenue: Existing\nBase","New Opt-In\nShare"]]
for d,oi,sp,nr,er,sh in optins: oh.append([d,n(oi),n(sp),n(nr),n(er),f"{sh:.2f}%"])
story += [table(oh,[20*mm,29*mm,26*mm,34*mm,38*mm,27*mm],6.8,["CENTER","RIGHT","RIGHT","RIGHT","RIGHT","RIGHT"]),
          Paragraph("New opt-ins remained small and volatile. Their share hit a low of 2.01% on 11 Oct, but the 14 Oct rebound was acquisition-led: same-day payer revenue rose Tsh 45,693 and explained 96.66% of that day's total increase.", styles["Smallx"]),
          Paragraph("4. Daily Payer-Flow Bridge", styles["H1x"])]
bh = [["Day","Gained\nPayers","Gained\nRevenue","Lost\nPayers","Lost Prior-Day\nRevenue","Retained-Payer\nChange","Net Change"]]
for d,gp,gr,lp,lr,rd,net in bridge: bh.append([d,n(gp),n(gr),n(lp),n(lr),n(rd),n(net)])
story += [table(bh,[17*mm,23*mm,28*mm,22*mm,31*mm,31*mm,26*mm],6.5,["CENTER","RIGHT","RIGHT","RIGHT","RIGHT","RIGHT","RIGHT"]),
          Paragraph("The payer base remained highly rotational: 21.0k–22.4k payers entered while 21.2k–22.8k prior-day payers dropped out each day. Retained payers reduced revenue every day; even the 14 Oct recovery depended on gained-payer revenue exceeding both lost revenue and a Tsh 676,416 retained-payer decline.", styles["Smallx"]),
          Paragraph("5. Why Prior-Day Payers Did Not Pay Next Day", styles["H1x"])]
root = [["Next Day","Lost Prior\nRevenue","Insufficient\nBalance","Other Failed /\nSystem","No Charge\nAttempt","MNO Churn\nOverlap","Self-Unsub\nOverlap"]]
root.append(["11 Oct","3,077,912","96.37%","0.03%","3.60%","42,591 / 280","700 / 5"])
root.append(["12 Oct","3,029,001","96.66%","0.00%","3.34%","36,341 / 235","500 / 3"])
root.append(["13 Oct","2,949,193","96.19%","0.02%","3.80%","38,339 / 241","1,120 / 9"])
story += [table(root,[21*mm,28*mm,25*mm,25*mm,23*mm,28*mm,27*mm],6.2,["CENTER","RIGHT","RIGHT","RIGHT","RIGHT","RIGHT","RIGHT"]),
          Paragraph("<b>Finding:</b> insufficient balance is the operational root cause. MNO churn and self-unsubscription figures are overlaps (revenue/customers), not additive categories. The 3.3%–3.8% no-attempt residual is the only material cohort requiring scheduler/subscriber-state investigation.", styles["Bodyx"])]

story.append(PageBreak()); story += [Paragraph("6. Product and Charging-Base Structure", styles["H1x"]), Paragraph("Daily SUCCESS revenue by billing plan and channel", styles["Bodyx"])]
ph = [["Date","P02 Revenue","P01 Revenue","P03 Revenue","Airtime Revenue","M-Pesa Revenue"]]
for row in plans: ph.append([row[0]]+[n(x) for x in row[1:]])
story += [table(ph,[20*mm,29*mm,29*mm,28*mm,35*mm,32*mm],6.8,["CENTER","RIGHT","RIGHT","RIGHT","RIGHT","RIGHT"]),
          Paragraph("P02 generated 67.3%–67.9% of revenue and drove the largest absolute movements; P01 supplied 27.3%–28.0%. Airtime accounted for 93.8%–94.0% of revenue. The 12–13 Oct slide was broad core-plan airtime weakness, not a payment-channel shift.", styles["Smallx"]),
          Paragraph("Classified successful payers by current P1–P8 segment", styles["H1x"])]
sh = [["Date","P1","P2","P3–P8 combined","No current subscriber row"]]
for d,p1,p2,other,un in classified_success: sh.append([d,n(p1),n(p2),n(other),n(un)])
story += [table(sh,[23*mm,28*mm,28*mm,39*mm,43*mm],7,["CENTER","RIGHT","RIGHT","RIGHT","RIGHT"]),
          Paragraph("Among successes that still match the 15 Oct subscriber snapshot, P1 supplies more than 99.7%. P3–P8 produce only 6–13 distinct successful payers per day combined. The unclassified gap fell from 1,625 payers on 10 Oct to 659 on 14 Oct, but remains a provisioning/data-lineage issue.", styles["Smallx"]),
          Paragraph("7. Subscriber Base vs. Billing Algorithm (P1–P8) — Live Snapshot", styles["H1x"])]
base = [["Priority","Segment","Doc Base (approx.)","Actual Base (15 Oct)","Change"]]
for p,desc,docb,actual in segments: base.append([p,desc,n(docb),n(actual),f"{100*(actual/docb-1):+.1f}%"])
base.append(["","TOTAL",n(sum(x[2] for x in segments)),n(sum(x[3] for x in segments)),f"{100*(sum(x[3] for x in segments)/sum(x[2] for x in segments)-1):+.1f}%"])
bt=table(base,[18*mm,52*mm,35*mm,38*mm,24*mm],6.8,["CENTER","LEFT","RIGHT","RIGHT","RIGHT"]); bt.setStyle(TableStyle([("FONTNAME",(0,-1),(-1,-1),"Helvetica-Bold"),("LINEABOVE",(0,-1),(-1,-1),1,NAVY)])); story.append(bt)
story.append(Paragraph("Material pattern: P7+P8 total 1,167,063 (54.8% of the live base) yet contribute effectively no daily success. P2 is 52.0% below the reference base, while P7 is 44.2% above it. This reinforces the conclusion that recovery capacity is concentrated on very low-yield cohorts.", styles["Smallx"]))

story.append(PageBreak()); story += [Paragraph("8. Operational Notes", styles["H1x"])]
ops = [
    "<b>No revenue-sized gateway incident:</b> 'other failed/system' statuses explain only 0.00%–0.03% of lost prior-day revenue on down days. The lower 14 Oct attempt count did not prevent revenue from recovering.",
    "<b>Insufficient balance remains the controlling constraint:</b> it explains roughly 96% of revenue lost when prior-day payers disappear. The billing algorithm's retry timing therefore matters more than increasing raw attempt volume.",
    "<b>Retained-payer erosion matters:</b> customers paying on consecutive days reduced revenue by Tsh 636k–718k daily. Positive days require unusually strong gained-payer inflow merely to offset this drag.",
    "<b>Scope continuity:</b> the new-opt-in method is identical to the earlier reports. P1–P8 classifications use the live 15 Oct snapshot, so historical membership can be reclassified by later charging or removal.",
]
for x in ops: story.append(Paragraph("• "+x, styles["Bodyx"]))
story.append(Paragraph("9. Way Forward", styles["H1x"]))
actions = [
    "<b>Optimize insufficient-balance recovery in P1/P02 first:</b> measure success by retry hour and concentrate retries around observed airtime availability; this is the only lever large enough to explain the daily swings.",
    "<b>Investigate the 3.3%–3.8% no-attempt residual:</b> reconcile next_billing_at, billing_holiday_until and subscriber presence for prior-day payers who receive no next-day attempt.",
    "<b>Throttle P3–P8 routine recovery:</b> these cohorts hold about 55% of the base but produced only 6–13 classified successful payers/day combined; preserve targeted experiments, not blanket retry load.",
    "<b>Track one daily bridge:</b> gained revenue, lost revenue, retained-payer change, and insufficient-balance loss by P02/P01. This will show immediately whether an uplift is recovery, acquisition, or a system issue.",
]
for x in actions: story.append(Paragraph("• "+x, styles["Bodyx"]))
story += [Spacer(1,5), Paragraph("Methodology", styles["H1x"]),
          Paragraph("Revenue is SUM(amount) for billing.icg_payments.status='SUCCESS' by created_at date. Attempts include all statuses. New opt-ins are distinct successful subscription.opt_in_logs MSISDNs on the same date; their same-day SUCCESS revenue is classified as new. Daily payer bridges compare distinct successful MSISDNs. Loss cause is mutually exclusive by charging outcome (insufficient balance, other failed/system, no attempt); churn and self-unsubscription are separately shown overlaps. Segment priority follows the supplied P1–P8 model using subscription.subscribers.last_charged_at and created_at, one highest-priority segment per MSISDN. Reference base figures are approximate.", styles["Smallx"]),
          Paragraph("Figures computed directly from the production PostgreSQL database on 15 Oct 2026. No customer identifiers or credentials are included.", styles["Smallx"])]

doc.build(story, onFirstPage=footer, onLaterPages=footer)
print(OUT)

from pathlib import Path

views = Path("app/src/main/java/com/mysdesign/formiva/FormivaViews.java")
replacement_file = Path("tools/ExerciseVisualView.motion.java.txt")
text = views.read_text(encoding="utf-8")
replacement = replacement_file.read_text(encoding="utf-8").rstrip() + "\n"
start_marker = "class ExerciseVisualView extends View {"
end_marker = "\nclass ProgressRingView extends View {"
if start_marker not in text or end_marker not in text:
    raise SystemExit("ExerciseVisualView replacement markers were not found")
start = text.index(start_marker)
end = text.index(end_marker)
views.write_text(text[:start] + replacement + text[end:], encoding="utf-8")

main = Path("app/src/main/java/com/mysdesign/formiva/FormivaMainActivity.java")
main_text = main.read_text(encoding="utf-8")
main_text = main_text.replace("Sürüm 1.1.0 test • MYS Design", "Sürüm 1.2.1 motion fix • MYS Design")
main.write_text(main_text, encoding="utf-8")

print("Applied Formiva anatomically guided motion animation patch")

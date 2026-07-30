package com.mysdesign.formiva;

import android.app.Activity;
import android.app.AlertDialog;
import android.content.SharedPreferences;
import android.content.res.ColorStateList;
import android.graphics.Color;
import android.graphics.Typeface;
import android.graphics.drawable.GradientDrawable;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.speech.tts.TextToSpeech;
import android.speech.tts.Voice;
import android.view.Gravity;
import android.view.View;
import android.widget.Button;
import android.widget.CheckBox;
import android.widget.LinearLayout;
import android.widget.ScrollView;
import android.widget.Space;
import android.widget.TextView;
import android.widget.Toast;

import java.util.ArrayList;
import java.util.Collections;
import java.util.List;
import java.util.Locale;

public class FormivaMainActivity extends Activity implements TextToSpeech.OnInitListener {
    private static final int NAVY = Color.rgb(11, 29, 58);
    private static final int BLUE = Color.rgb(17, 77, 216);
    private static final int CYAN = Color.rgb(0, 194, 255);
    private static final int MINT = Color.rgb(78, 215, 168);
    private static final int BG = Color.rgb(247, 250, 252);
    private static final int MUTED = Color.rgb(102, 112, 133);
    private static final int BORDER = Color.rgb(228, 233, 239);

    private static final String[] DAYS = {"Pzt", "Sal", "Çar", "Per", "Cum", "Cmt", "Paz"};
    private static final String[] DAY_KEYS = {"day_mon", "day_tue", "day_wed", "day_thu", "day_fri", "day_sat", "day_sun"};

    private SharedPreferences prefs;
    private TextToSpeech tts;
    private final Handler handler = new Handler(Looper.getMainLooper());
    private Runnable timerRunnable;
    private boolean timerRunning;
    private int seconds;
    private int totalSeconds;
    private String screen = "home";

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        getWindow().setStatusBarColor(Color.WHITE);
        getWindow().setNavigationBarColor(Color.WHITE);
        getWindow().getDecorView().setSystemUiVisibility(View.SYSTEM_UI_FLAG_LIGHT_STATUS_BAR);
        prefs = getSharedPreferences("formiva", MODE_PRIVATE);
        initializeDefaults();
        tts = new TextToSpeech(this, this);
        if (prefs.getBoolean("onboarding_done", false)) showHome();
        else showOnboarding();
    }

    private void initializeDefaults() {
        if (!prefs.getBoolean("days_initialized", false)) {
            prefs.edit()
                    .putBoolean("day_thu", true)
                    .putBoolean("day_fri", true)
                    .putBoolean("days_initialized", true)
                    .putBoolean("pelvic", true)
                    .putString("coach_gender", "male")
                    .apply();
        }
    }

    @Override
    public void onInit(int status) {
        if (status != TextToSpeech.SUCCESS) return;
        tts.setLanguage(new Locale("tr", "TR"));
        tts.setSpeechRate(0.90f);
        tts.setPitch(0.98f);
        selectNaturalTurkishVoice();
    }

    private void selectNaturalTurkishVoice() {
        if (tts == null || tts.getVoices() == null) return;
        Voice best = null;
        int bestScore = Integer.MIN_VALUE;
        for (Voice voice : tts.getVoices()) {
            if (voice.getLocale() == null || !"tr".equalsIgnoreCase(voice.getLocale().getLanguage())) continue;
            String name = voice.getName() == null ? "" : voice.getName().toLowerCase(Locale.ROOT);
            int score = voice.getQuality() * 10;
            if (voice.isNetworkConnectionRequired()) score += 12;
            if (name.contains("neural") || name.contains("natural") || name.contains("premium")) score += 30;
            if (score > bestScore) {
                bestScore = score;
                best = voice;
            }
        }
        if (best != null) tts.setVoice(best);
    }

    private void speak(String value) {
        if (tts != null) tts.speak(value, TextToSpeech.QUEUE_FLUSH, null, "formiva_voice");
    }

    private boolean maleCoach() {
        return !"female".equals(prefs.getString("coach_gender", "male"));
    }

    private int dp(int value) {
        return Math.round(value * getResources().getDisplayMetrics().density);
    }

    private GradientDrawable round(int color, int radius) {
        GradientDrawable drawable = new GradientDrawable();
        drawable.setColor(color);
        drawable.setCornerRadius(dp(radius));
        return drawable;
    }

    private GradientDrawable outline(int color, int radius, int strokeColor, int stroke) {
        GradientDrawable drawable = round(color, radius);
        drawable.setStroke(dp(stroke), strokeColor);
        return drawable;
    }

    private GradientDrawable primaryBackground() {
        GradientDrawable drawable = new GradientDrawable(
                GradientDrawable.Orientation.LEFT_RIGHT,
                new int[]{BLUE, CYAN, MINT});
        drawable.setCornerRadius(dp(18));
        return drawable;
    }

    private TextView label(String value, int size, int color, boolean bold) {
        TextView view = new TextView(this);
        view.setText(value);
        view.setTextSize(size);
        view.setTextColor(color);
        view.setLineSpacing(0f, 1.08f);
        view.setTypeface(Typeface.create("sans-serif", bold ? Typeface.BOLD : Typeface.NORMAL));
        return view;
    }

    private TextView title(String value, int size) {
        TextView view = label(value, size, NAVY, true);
        view.setPadding(0, dp(4), 0, dp(4));
        return view;
    }

    private TextView description(String value, int size) {
        TextView view = label(value, size, MUTED, false);
        view.setPadding(0, dp(3), 0, dp(3));
        return view;
    }

    private LinearLayout content() {
        LinearLayout root = new LinearLayout(this);
        root.setOrientation(LinearLayout.VERTICAL);
        root.setPadding(dp(18), dp(10), dp(18), dp(26));
        root.setBackgroundColor(BG);
        return root;
    }

    private LinearLayout card() {
        LinearLayout card = new LinearLayout(this);
        card.setOrientation(LinearLayout.VERTICAL);
        card.setPadding(dp(17), dp(16), dp(17), dp(16));
        card.setBackground(round(Color.WHITE, 22));
        card.setElevation(dp(2));
        LinearLayout.LayoutParams params = new LinearLayout.LayoutParams(
                LinearLayout.LayoutParams.MATCH_PARENT,
                LinearLayout.LayoutParams.WRAP_CONTENT);
        params.setMargins(0, dp(7), 0, dp(7));
        card.setLayoutParams(params);
        return card;
    }

    private Button primary(String value) {
        Button button = new Button(this);
        button.setText(value);
        button.setTextColor(Color.WHITE);
        button.setTextSize(16);
        button.setTypeface(Typeface.create("sans-serif", Typeface.BOLD));
        button.setAllCaps(false);
        button.setStateListAnimator(null);
        button.setBackground(primaryBackground());
        LinearLayout.LayoutParams params = new LinearLayout.LayoutParams(
                LinearLayout.LayoutParams.MATCH_PARENT, dp(56));
        params.setMargins(0, dp(8), 0, dp(8));
        button.setLayoutParams(params);
        return button;
    }

    private Button secondary(String value) {
        Button button = new Button(this);
        button.setText(value);
        button.setTextColor(NAVY);
        button.setTextSize(14);
        button.setTypeface(Typeface.create("sans-serif", Typeface.BOLD));
        button.setAllCaps(false);
        button.setStateListAnimator(null);
        button.setBackground(outline(Color.WHITE, 18, BORDER, 1));
        LinearLayout.LayoutParams params = new LinearLayout.LayoutParams(
                LinearLayout.LayoutParams.MATCH_PARENT, dp(52));
        params.setMargins(0, dp(5), 0, dp(5));
        button.setLayoutParams(params);
        return button;
    }

    private void brand(LinearLayout root, boolean compact) {
        FormivaLogoView logo = new FormivaLogoView(this);
        root.addView(logo, new LinearLayout.LayoutParams(
                LinearLayout.LayoutParams.MATCH_PARENT, dp(compact ? 76 : 96)));
    }

    private void render(LinearLayout page, int active) {
        LinearLayout shell = new LinearLayout(this);
        shell.setOrientation(LinearLayout.VERTICAL);
        shell.setBackgroundColor(BG);
        ScrollView scroll = new ScrollView(this);
        scroll.setFillViewport(true);
        scroll.addView(page);
        shell.addView(scroll, new LinearLayout.LayoutParams(
                LinearLayout.LayoutParams.MATCH_PARENT, 0, 1f));
        if (active >= 0) shell.addView(navigation(active));
        setContentView(shell);
    }

    private LinearLayout navigation(int active) {
        LinearLayout navigation = new LinearLayout(this);
        navigation.setOrientation(LinearLayout.HORIZONTAL);
        navigation.setGravity(Gravity.CENTER);
        navigation.setPadding(dp(4), dp(5), dp(4), dp(6));
        navigation.setBackgroundColor(Color.WHITE);
        navigation.setElevation(dp(12));
        String[] icons = {"⌂", "▦", "◎", "▥", "◉"};
        String[] names = {"Ana Sayfa", "Program", "Egzersizler", "Raporlar", "Profil"};
        View.OnClickListener[] listeners = {
                v -> showHome(), v -> showProgram(), v -> showLibrary(), v -> showReports(), v -> showProfile()
        };
        for (int i = 0; i < names.length; i++) {
            LinearLayout item = new LinearLayout(this);
            item.setOrientation(LinearLayout.VERTICAL);
            item.setGravity(Gravity.CENTER);
            TextView icon = label(icons[i], 20, i == active ? BLUE : MUTED, true);
            icon.setGravity(Gravity.CENTER);
            TextView name = label(names[i], 10, i == active ? NAVY : MUTED, i == active);
            name.setGravity(Gravity.CENTER);
            item.addView(icon, new LinearLayout.LayoutParams(dp(34), dp(29)));
            item.addView(name);
            item.setOnClickListener(listeners[i]);
            navigation.addView(item, new LinearLayout.LayoutParams(0, dp(58), 1f));
        }
        return navigation;
    }

    private TextView chip(String value, boolean selected) {
        TextView chip = label(value, 13, selected ? NAVY : MUTED, selected);
        chip.setGravity(Gravity.CENTER);
        chip.setPadding(dp(8), dp(8), dp(8), dp(8));
        chip.setBackground(outline(selected ? Color.rgb(232, 249, 244) : Color.WHITE,
                16, selected ? MINT : BORDER, selected ? 2 : 1));
        return chip;
    }

    private void updateChip(TextView chip, boolean selected) {
        chip.setTextColor(selected ? NAVY : MUTED);
        chip.setTypeface(Typeface.create("sans-serif", selected ? Typeface.BOLD : Typeface.NORMAL));
        chip.setBackground(outline(selected ? Color.rgb(232, 249, 244) : Color.WHITE,
                16, selected ? MINT : BORDER, selected ? 2 : 1));
    }

    private void showOnboarding() {
        screen = "onboarding";
        stopTimer();
        LinearLayout root = content();
        brand(root, false);
        root.addView(title("Size en uygun koçu seçin", 27));
        root.addView(description("Seçimine göre egzersizlerde erkek veya kadın manken kullanılır ve koç deneyimi kişiselleştirilir.", 15));
        final boolean[] selectedMale = {maleCoach()};

        LinearLayout row = new LinearLayout(this);
        row.setOrientation(LinearLayout.HORIZONTAL);
        row.setPadding(0, dp(12), 0, dp(10));
        LinearLayout male = coachCard(true, selectedMale[0]);
        LinearLayout female = coachCard(false, !selectedMale[0]);
        row.addView(male, new LinearLayout.LayoutParams(0, dp(250), 1f));
        row.addView(new Space(this), new LinearLayout.LayoutParams(dp(10), 1));
        row.addView(female, new LinearLayout.LayoutParams(0, dp(250), 1f));
        root.addView(row);

        male.setOnClickListener(v -> {
            selectedMale[0] = true;
            refreshCoach(male, true);
            refreshCoach(female, false);
        });
        female.setOnClickListener(v -> {
            selectedMale[0] = false;
            refreshCoach(male, false);
            refreshCoach(female, true);
        });

        Button continueButton = primary("Devam Et");
        continueButton.setOnClickListener(v -> {
            prefs.edit()
                    .putString("coach_gender", selectedMale[0] ? "male" : "female")
                    .putBoolean("onboarding_done", true)
                    .apply();
            selectNaturalTurkishVoice();
            showHome();
        });
        root.addView(continueButton);
        TextView note = description("Bu seçimi Profil ekranından değiştirebilirsin.", 12);
        note.setGravity(Gravity.CENTER);
        root.addView(note);
        render(root, -1);
    }

    private LinearLayout coachCard(boolean male, boolean selected) {
        LinearLayout card = new LinearLayout(this);
        card.setOrientation(LinearLayout.VERTICAL);
        card.setGravity(Gravity.CENTER_HORIZONTAL);
        card.setPadding(dp(7), dp(7), dp(7), dp(9));
        card.setBackground(outline(Color.WHITE, 24, selected ? MINT : BORDER, selected ? 2 : 1));
        CoachAvatarView avatar = new CoachAvatarView(this, male);
        avatar.setSelectedState(selected);
        card.addView(avatar, new LinearLayout.LayoutParams(
                LinearLayout.LayoutParams.MATCH_PARENT, 0, 1f));
        TextView name = title(male ? "Erkek Koç" : "Kadın Koç", 16);
        name.setGravity(Gravity.CENTER);
        card.addView(name);
        return card;
    }

    private void refreshCoach(LinearLayout card, boolean selected) {
        card.setBackground(outline(Color.WHITE, 24, selected ? MINT : BORDER, selected ? 2 : 1));
        if (card.getChildAt(0) instanceof CoachAvatarView) {
            ((CoachAvatarView) card.getChildAt(0)).setSelectedState(selected);
        }
    }

    private void showHome() {
        screen = "home";
        stopTimer();
        LinearLayout root = content();
        brand(root, true);
        root.addView(title("Merhaba! 👋", 24));
        root.addView(description("Bugünkü programın hazır. Kontrollü ilerle, gelişimini düzenli takip et.", 14));

        LinearLayout summary = card();
        summary.addView(title("Bugünkü Özet", 18));
        LinearLayout row = new LinearLayout(this);
        row.setOrientation(LinearLayout.HORIZONTAL);
        ProgressRingView ring = new ProgressRingView(this);
        ring.setValues("12", "Tekrar", 0.72f);
        row.addView(ring, new LinearLayout.LayoutParams(dp(135), dp(135)));
        LinearLayout stats = new LinearLayout(this);
        stats.setOrientation(LinearLayout.VERTICAL);
        stats.setPadding(dp(14), 0, 0, 0);
        stats.addView(stat("Antrenman", "245 kcal"));
        stats.addView(stat("Süre", "35 dk"));
        stats.addView(stat("İlerleme", "%" + score()));
        row.addView(stats, new LinearLayout.LayoutParams(0, dp(135), 1f));
        summary.addView(row);
        root.addView(summary);

        LinearLayout week = card();
        week.addView(title("Bu Haftanın Programı", 18));
        LinearLayout days = new LinearLayout(this);
        days.setOrientation(LinearLayout.HORIZONTAL);
        for (int i = 0; i < DAYS.length; i++) {
            boolean selected = prefs.getBoolean(DAY_KEYS[i], false);
            TextView day = label(DAYS[i], 11, selected ? Color.WHITE : MUTED, selected);
            day.setGravity(Gravity.CENTER);
            day.setBackground(round(selected ? (i % 2 == 0 ? BLUE : MINT) : Color.rgb(243, 247, 249), 18));
            LinearLayout.LayoutParams params = new LinearLayout.LayoutParams(0, dp(40), 1f);
            params.setMargins(dp(2), 0, dp(2), 0);
            days.addView(day, params);
        }
        week.addView(days);
        root.addView(week);

        LinearLayout workout = card();
        workout.addView(label("BANA ÖZEL", 12, MINT, true));
        workout.addView(title("Evde Güçlenme Programı", 22));
        workout.addView(description("Güç, core, kondisyon ve seçimine göre pelvik taban-nefes desteği.", 14));
        ExerciseVisualView preview = new ExerciseVisualView(this);
        preview.setExercise(1, maleCoach());
        workout.addView(preview, new LinearLayout.LayoutParams(
                LinearLayout.LayoutParams.MATCH_PARENT, dp(195)));
        Button start = primary("Antrenmana Başla");
        start.setOnClickListener(v -> startWorkout(ExerciseData.personalPlan(prefs.getBoolean("pelvic", true))));
        workout.addView(start);
        root.addView(workout);

        LinearLayout buttons = new LinearLayout(this);
        buttons.setOrientation(LinearLayout.HORIZONTAL);
        Button edit = secondary("Programı Düzenle");
        edit.setOnClickListener(v -> showProgram());
        Button manual = secondary("Manuel Hareket");
        manual.setOnClickListener(v -> showManual());
        buttons.addView(edit, new LinearLayout.LayoutParams(0, dp(60), 1f));
        buttons.addView(new Space(this), new LinearLayout.LayoutParams(dp(8), 1));
        buttons.addView(manual, new LinearLayout.LayoutParams(0, dp(60), 1f));
        root.addView(buttons);

        LinearLayout safety = card();
        safety.addView(title("Güvenli Başlangıç", 17));
        safety.addView(description("Göğüs ağrısı, ciddi nefes darlığı, baş dönmesi veya keskin ağrı olursa antrenmanı durdur.", 13));
        root.addView(safety);
        render(root, 0);
    }

    private LinearLayout stat(String name, String value) {
        LinearLayout row = new LinearLayout(this);
        row.setOrientation(LinearLayout.HORIZONTAL);
        row.setGravity(Gravity.CENTER_VERTICAL);
        TextView left = description(name, 13);
        TextView right = label(value, 14, NAVY, true);
        right.setGravity(Gravity.CENTER_VERTICAL | Gravity.END);
        row.addView(left, new LinearLayout.LayoutParams(0, dp(40), 1f));
        row.addView(right, new LinearLayout.LayoutParams(0, dp(40), 1f));
        return row;
    }

    private int score() {
        return Math.min(100, 45 + prefs.getInt("completed", 0) * 9);
    }

    private void showProgram() {
        screen = "program";
        stopTimer();
        LinearLayout root = content();
        brand(root, true);
        root.addView(title("Akıllı Program Oluşturucu", 24));
        root.addView(description("Antrenman günlerini ve destek hedefini belirle.", 14));

        LinearLayout goals = card();
        goals.addView(title("Hedefin", 18));
        String[] goalNames = {"Güçlenmek", "Kas Kazanmak", "Formda Kalmak"};
        final int[] selectedGoal = {prefs.getInt("goal_index", 0)};
        LinearLayout goalRow = new LinearLayout(this);
        goalRow.setOrientation(LinearLayout.HORIZONTAL);
        TextView[] goalChips = new TextView[goalNames.length];
        for (int i = 0; i < goalNames.length; i++) {
            goalChips[i] = chip(goalNames[i], selectedGoal[0] == i);
            final int index = i;
            goalChips[i].setOnClickListener(v -> {
                selectedGoal[0] = index;
                for (int j = 0; j < goalChips.length; j++) updateChip(goalChips[j], selectedGoal[0] == j);
            });
            goalRow.addView(goalChips[i], new LinearLayout.LayoutParams(0, dp(52), 1f));
        }
        goals.addView(goalRow);
        root.addView(goals);

        LinearLayout daysCard = card();
        daysCard.addView(title("Antrenman Günlerin", 18));
        boolean[] selectedDays = new boolean[7];
        TextView[] dayChips = new TextView[7];
        LinearLayout dayRow = new LinearLayout(this);
        dayRow.setOrientation(LinearLayout.HORIZONTAL);
        for (int i = 0; i < 7; i++) {
            selectedDays[i] = prefs.getBoolean(DAY_KEYS[i], false);
            dayChips[i] = chip(DAYS[i], selectedDays[i]);
            final int index = i;
            dayChips[i].setOnClickListener(v -> {
                selectedDays[index] = !selectedDays[index];
                updateChip(dayChips[index], selectedDays[index]);
            });
            dayRow.addView(dayChips[i], new LinearLayout.LayoutParams(0, dp(52), 1f));
        }
        daysCard.addView(dayRow);
        root.addView(daysCard);

        LinearLayout extras = card();
        extras.addView(title("Program Tercihleri", 18));
        CheckBox pelvic = new CheckBox(this);
        pelvic.setText("Pelvik taban, gevşeme ve nefes desteğini ekle");
        pelvic.setTextSize(14);
        pelvic.setTextColor(NAVY);
        pelvic.setChecked(prefs.getBoolean("pelvic", true));
        pelvic.setButtonTintList(new ColorStateList(
                new int[][]{new int[]{android.R.attr.state_checked}, new int[]{}},
                new int[]{MINT, MUTED}));
        extras.addView(pelvic);
        root.addView(extras);

        Button save = primary("Planımı Oluştur");
        save.setOnClickListener(v -> {
            boolean any = false;
            SharedPreferences.Editor editor = prefs.edit();
            for (int i = 0; i < selectedDays.length; i++) {
                editor.putBoolean(DAY_KEYS[i], selectedDays[i]);
                any |= selectedDays[i];
            }
            if (!any) {
                Toast.makeText(this, "En az bir gün seçmelisin.", Toast.LENGTH_LONG).show();
                return;
            }
            editor.putInt("goal_index", selectedGoal[0]);
            editor.putBoolean("pelvic", pelvic.isChecked());
            editor.apply();
            Toast.makeText(this, "Programın güncellendi.", Toast.LENGTH_SHORT).show();
            showHome();
        });
        root.addView(save);
        render(root, 1);
    }

    private void showLibrary() {
        screen = "library";
        stopTimer();
        LinearLayout root = content();
        brand(root, true);
        root.addView(title("Egzersizler", 24));
        root.addView(description("Her hareketi seçtiğin cinsiyete uygun animasyonlu mankenle incele.", 14));
        for (int i = 0; i < ExerciseData.NAMES.length; i++) {
            final int index = i;
            LinearLayout item = card();
            item.setOrientation(LinearLayout.HORIZONTAL);
            ExerciseVisualView visual = new ExerciseVisualView(this);
            visual.setExercise(i, maleCoach());
            item.addView(visual, new LinearLayout.LayoutParams(dp(118), dp(112)));
            LinearLayout info = new LinearLayout(this);
            info.setOrientation(LinearLayout.VERTICAL);
            info.setPadding(dp(14), 0, 0, 0);
            info.addView(title(ExerciseData.NAMES[i], 16));
            info.addView(description(ExerciseData.CATEGORIES[i] + " • " + ExerciseData.SECONDS[i] + " sn", 12));
            info.addView(label("Gör ve Başlat  ›", 13, BLUE, true));
            item.addView(info, new LinearLayout.LayoutParams(0, dp(112), 1f));
            item.setOnClickListener(v -> showExercise(index));
            root.addView(item);
        }
        Button manual = secondary("Manuel Program Oluştur");
        manual.setOnClickListener(v -> showManual());
        root.addView(manual);
        render(root, 2);
    }

    private void showExercise(int index) {
        screen = "exercise";
        stopTimer();
        LinearLayout root = content();
        brand(root, true);
        root.addView(title(ExerciseData.NAMES[index], 24));
        root.addView(description(ExerciseData.CATEGORIES[index] + " • " + ExerciseData.SECONDS[index] + " saniye", 14));
        ExerciseVisualView visual = new ExerciseVisualView(this);
        visual.setExercise(index, maleCoach());
        root.addView(visual, new LinearLayout.LayoutParams(
                LinearLayout.LayoutParams.MATCH_PARENT, dp(320)));
        LinearLayout instruction = card();
        instruction.addView(title("Doğru Form", 18));
        instruction.addView(description(ExerciseData.CUES[index], 15));
        root.addView(instruction);
        Button start = primary("Bu Hareketi Başlat");
        start.setOnClickListener(v -> {
            List<Integer> one = new ArrayList<>();
            one.add(index);
            startWorkout(one);
        });
        root.addView(start);
        render(root, 2);
    }

    private void showManual() {
        screen = "manual";
        stopTimer();
        LinearLayout root = content();
        brand(root, true);
        root.addView(title("Manuel Hareket Seçimi", 23));
        root.addView(description("Formiva seçtiğin hareketleri uygun antrenman sırasına yerleştirir.", 14));
        CheckBox[] checks = new CheckBox[ExerciseData.NAMES.length];
        for (int i = 0; i < ExerciseData.NAMES.length; i++) {
            LinearLayout item = card();
            item.setOrientation(LinearLayout.HORIZONTAL);
            CheckBox check = new CheckBox(this);
            check.setButtonTintList(new ColorStateList(
                    new int[][]{new int[]{android.R.attr.state_checked}, new int[]{}},
                    new int[]{MINT, MUTED}));
            checks[i] = check;
            item.addView(check, new LinearLayout.LayoutParams(dp(46), dp(60)));
            LinearLayout info = new LinearLayout(this);
            info.setOrientation(LinearLayout.VERTICAL);
            info.addView(title(ExerciseData.NAMES[i], 16));
            info.addView(description(ExerciseData.CATEGORIES[i] + " • " + ExerciseData.SECONDS[i] + " sn", 12));
            item.addView(info, new LinearLayout.LayoutParams(0, dp(64), 1f));
            item.setOnClickListener(v -> check.setChecked(!check.isChecked()));
            root.addView(item);
        }
        Button create = primary("Programı Oluştur ve Başlat");
        create.setOnClickListener(v -> {
            List<Integer> plan = new ArrayList<>();
            for (int i = 0; i < checks.length; i++) if (checks[i].isChecked()) plan.add(i);
            if (plan.isEmpty()) {
                Toast.makeText(this, "En az bir hareket seçmelisin.", Toast.LENGTH_LONG).show();
                return;
            }
            Collections.sort(plan, (a, b) -> Integer.compare(
                    ExerciseData.orderForCategory(ExerciseData.CATEGORIES[a]),
                    ExerciseData.orderForCategory(ExerciseData.CATEGORIES[b])));
            startWorkout(plan);
        });
        root.addView(create);
        render(root, 2);
    }

    private void showReports() {
        screen = "reports";
        stopTimer();
        LinearLayout root = content();
        brand(root, true);
        root.addView(title("Raporlar", 24));
        root.addView(description("İlerlemeni takip et, sonuçlarını gör.", 14));

        LinearLayout progress = card();
        progress.addView(title("Devamlılık", 18));
        LinearLayout row = new LinearLayout(this);
        row.setOrientation(LinearLayout.HORIZONTAL);
        ProgressRingView ring = new ProgressRingView(this);
        ring.setValues("%" + score(), "Skor", score() / 100f);
        row.addView(ring, new LinearLayout.LayoutParams(dp(150), dp(150)));
        LinearLayout stats = new LinearLayout(this);
        stats.setOrientation(LinearLayout.VERTICAL);
        stats.setPadding(dp(14), 0, 0, 0);
        stats.addView(stat("Toplam Antrenman", String.valueOf(prefs.getInt("completed", 0))));
        stats.addView(stat("Toplam Süre", prefs.getInt("completed", 0) * 35 + " dk"));
        stats.addView(stat("Haftalık Hedef", dayCount() + " gün"));
        row.addView(stats, new LinearLayout.LayoutParams(0, dp(150), 1f));
        progress.addView(row);
        root.addView(progress);

        LinearLayout chartCard = card();
        chartCard.addView(title("Gelişim Eğilimi", 18));
        chartCard.addView(description("Son yedi kayıt", 12));
        MiniLineChartView chart = new MiniLineChartView(this);
        chart.setValues(new float[]{0.84f, 0.76f, 0.66f, 0.58f, 0.48f, 0.35f, 0.25f});
        chartCard.addView(chart, new LinearLayout.LayoutParams(
                LinearLayout.LayoutParams.MATCH_PARENT, dp(150)));
        root.addView(chartCard);

        LinearLayout metrics = new LinearLayout(this);
        metrics.setOrientation(LinearLayout.HORIZONTAL);
        metrics.addView(metric("Kilo", "66.0 kg", "Başlangıç"), new LinearLayout.LayoutParams(0, LinearLayout.LayoutParams.WRAP_CONTENT, 1f));
        metrics.addView(new Space(this), new LinearLayout.LayoutParams(dp(8), 1));
        metrics.addView(metric("Plank", "30 sn", "Son kayıt"), new LinearLayout.LayoutParams(0, LinearLayout.LayoutParams.WRAP_CONTENT, 1f));
        root.addView(metrics);
        render(root, 3);
    }

    private LinearLayout metric(String name, String value, String detail) {
        LinearLayout metric = card();
        metric.addView(description(name, 12));
        metric.addView(title(value, 22));
        metric.addView(description(detail, 11));
        return metric;
    }

    private int dayCount() {
        int count = 0;
        for (String key : DAY_KEYS) if (prefs.getBoolean(key, false)) count++;
        return count;
    }

    private void showProfile() {
        screen = "profile";
        stopTimer();
        LinearLayout root = content();
        brand(root, true);
        root.addView(title("Profil ve Koç Ayarları", 23));
        LinearLayout coach = card();
        coach.addView(title("Seçili Koç", 18));
        CoachAvatarView avatar = new CoachAvatarView(this, maleCoach());
        avatar.setSelectedState(true);
        coach.addView(avatar, new LinearLayout.LayoutParams(
                LinearLayout.LayoutParams.MATCH_PARENT, dp(210)));
        TextView type = title(maleCoach() ? "Erkek Koç" : "Kadın Koç", 18);
        type.setGravity(Gravity.CENTER);
        coach.addView(type);
        Button change = secondary("Koçu Değiştir");
        change.setOnClickListener(v -> showOnboarding());
        coach.addView(change);
        root.addView(coach);

        LinearLayout voice = card();
        voice.addView(title("Doğal Sesli Anlatım", 18));
        voice.addView(description("Cihazındaki en kaliteli Türkçe ses otomatik seçilir. Tamamen gerçek insan sesi için ayrıca stüdyo kayıtları eklenmelidir.", 13));
        WaveformView waveform = new WaveformView(this);
        voice.addView(waveform, new LinearLayout.LayoutParams(
                LinearLayout.LayoutParams.MATCH_PARENT, dp(54)));
        Button sample = secondary("Ses Örneğini Dinle");
        sample.setOnClickListener(v -> speak("Merhaba. Ben Formiva koçun. Hareket boyunca nefesini ve formunu birlikte kontrol edeceğiz."));
        voice.addView(sample);
        root.addView(voice);

        LinearLayout about = card();
        about.addView(title("Formiva", 18));
        about.addView(description("Sürüm 1.1.0 test • MYS Design", 13));
        root.addView(about);
        render(root, 4);
    }

    private void startWorkout(List<Integer> plan) {
        if (plan == null || plan.isEmpty()) {
            Toast.makeText(this, "Program boş.", Toast.LENGTH_LONG).show();
            return;
        }
        workoutStep(plan, 0);
    }

    private void workoutStep(List<Integer> plan, int position) {
        screen = "workout";
        stopTimer();
        int index = plan.get(position);
        totalSeconds = ExerciseData.SECONDS[index];
        seconds = totalSeconds;
        LinearLayout root = content();

        LinearLayout header = new LinearLayout(this);
        header.setOrientation(LinearLayout.HORIZONTAL);
        TextView close = label("×", 30, NAVY, false);
        close.setGravity(Gravity.CENTER);
        close.setOnClickListener(v -> showHome());
        header.addView(close, new LinearLayout.LayoutParams(dp(48), dp(54)));
        LinearLayout headerInfo = new LinearLayout(this);
        headerInfo.setOrientation(LinearLayout.VERTICAL);
        headerInfo.addView(title(ExerciseData.NAMES[index], 21));
        headerInfo.addView(description((position + 1) + " / " + plan.size() + " • " + ExerciseData.CATEGORIES[index], 12));
        header.addView(headerInfo, new LinearLayout.LayoutParams(0, dp(62), 1f));
        root.addView(header);

        ExerciseVisualView visual = new ExerciseVisualView(this);
        visual.setExercise(index, maleCoach());
        root.addView(visual, new LinearLayout.LayoutParams(
                LinearLayout.LayoutParams.MATCH_PARENT, dp(330)));

        LinearLayout timer = card();
        timer.setGravity(Gravity.CENTER_HORIZONTAL);
        ProgressRingView ring = new ProgressRingView(this);
        ring.setValues(String.valueOf(seconds), "Saniye", 1f);
        timer.addView(ring, new LinearLayout.LayoutParams(dp(170), dp(170)));
        int reps = ExerciseData.DEFAULT_REPS[index];
        TextView detail = title(reps > 0 ? "3 Set • " + reps + " Tekrar" : "Kontrollü tempo", 16);
        detail.setGravity(Gravity.CENTER);
        timer.addView(detail);
        root.addView(timer);

        LinearLayout technique = card();
        technique.addView(title("Doğru Form", 18));
        technique.addView(description(ExerciseData.CUES[index], 15));
        root.addView(technique);

        LinearLayout voice = card();
        voice.addView(title("Doğal Sesli Koç", 17));
        LinearLayout voiceRow = new LinearLayout(this);
        voiceRow.setOrientation(LinearLayout.HORIZONTAL);
        CoachAvatarView avatar = new CoachAvatarView(this, maleCoach());
        avatar.setSelectedState(true);
        voiceRow.addView(avatar, new LinearLayout.LayoutParams(dp(72), dp(72)));
        WaveformView waveform = new WaveformView(this);
        voiceRow.addView(waveform, new LinearLayout.LayoutParams(0, dp(70), 1f));
        voice.addView(voiceRow);
        Button listen = secondary("Teknik Anlatımı Dinle");
        listen.setOnClickListener(v -> speak(ExerciseData.NAMES[index] + ". " + ExerciseData.CUES[index]));
        voice.addView(listen);
        root.addView(voice);

        Button play = primary("Başlat");
        play.setOnClickListener(v -> toggleTimer(play, ring));
        root.addView(play);

        LinearLayout controls = new LinearLayout(this);
        controls.setOrientation(LinearLayout.HORIZONTAL);
        if (position > 0) {
            Button previous = secondary("Önceki");
            previous.setOnClickListener(v -> workoutStep(plan, position - 1));
            controls.addView(previous, new LinearLayout.LayoutParams(0, dp(60), 1f));
            controls.addView(new Space(this), new LinearLayout.LayoutParams(dp(8), 1));
        }
        Button next = secondary(position < plan.size() - 1 ? "Sonraki Hareket" : "Antrenmanı Bitir");
        next.setOnClickListener(v -> {
            if (position < plan.size() - 1) workoutStep(plan, position + 1);
            else finishWorkout();
        });
        controls.addView(next, new LinearLayout.LayoutParams(0, dp(60), 1f));
        root.addView(controls);
        render(root, -1);
        speak("Sıradaki hareket " + ExerciseData.NAMES[index] + ". " + ExerciseData.CUES[index]);
    }

    private void toggleTimer(Button button, ProgressRingView ring) {
        if (timerRunning) {
            stopTimer();
            button.setText("Devam Et");
            return;
        }
        if (seconds <= 0) seconds = totalSeconds;
        timerRunning = true;
        button.setText("Duraklat");
        timerRunnable = new Runnable() {
            @Override
            public void run() {
                if (!timerRunning) return;
                if (seconds > 0) {
                    seconds--;
                    ring.setValues(String.valueOf(seconds), "Saniye", seconds / (float) totalSeconds);
                    if (seconds == 5) speak("Son beş saniye. Formunu koru.");
                    handler.postDelayed(this, 1000);
                } else {
                    timerRunning = false;
                    button.setText("Tamamlandı");
                    speak("Hareket tamamlandı. Kontrollü şekilde dinlen.");
                }
            }
        };
        handler.postDelayed(timerRunnable, 1000);
    }

    private void finishWorkout() {
        stopTimer();
        prefs.edit().putInt("completed", prefs.getInt("completed", 0) + 1).apply();
        speak("Antrenman tamamlandı. Tebrikler.");
        new AlertDialog.Builder(this)
                .setTitle("Antrenman tamamlandı")
                .setMessage("Antrenmanın rapor ekranına kaydedildi.")
                .setPositiveButton("Raporları Gör", (dialog, which) -> showReports())
                .setNegativeButton("Ana Sayfa", (dialog, which) -> showHome())
                .setCancelable(false)
                .show();
    }

    private void stopTimer() {
        timerRunning = false;
        if (timerRunnable != null) handler.removeCallbacks(timerRunnable);
    }

    @Override
    public void onBackPressed() {
        if ("home".equals(screen)) super.onBackPressed();
        else showHome();
    }

    @Override
    protected void onDestroy() {
        stopTimer();
        if (tts != null) {
            tts.stop();
            tts.shutdown();
        }
        super.onDestroy();
    }
}
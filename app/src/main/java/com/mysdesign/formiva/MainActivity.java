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
import android.view.ViewGroup;
import android.widget.Button;
import android.widget.CheckBox;
import android.widget.FrameLayout;
import android.widget.HorizontalScrollView;
import android.widget.LinearLayout;
import android.widget.ScrollView;
import android.widget.Space;
import android.widget.TextView;
import android.widget.Toast;

import java.util.ArrayList;
import java.util.Collections;
import java.util.List;
import java.util.Locale;
import java.util.Set;

public class MainActivity extends Activity implements TextToSpeech.OnInitListener {
    private static final int NAVY = Color.rgb(11, 29, 58);
    private static final int BLUE = Color.rgb(17, 77, 216);
    private static final int CYAN = Color.rgb(0, 194, 255);
    private static final int MINT = Color.rgb(78, 215, 168);
    private static final int BG = Color.rgb(247, 250, 252);
    private static final int MUTED = Color.rgb(102, 112, 133);
    private static final int BORDER = Color.rgb(228, 233, 239);
    private static final int SUCCESS = Color.rgb(32, 192, 138);

    private static final String[] DAY_NAMES = {"Pzt", "Sal", "Çar", "Per", "Cum", "Cmt", "Paz"};
    private static final String[] DAY_KEYS = {"day_mon", "day_tue", "day_wed", "day_thu", "day_fri", "day_sat", "day_sun"};

    private SharedPreferences prefs;
    private TextToSpeech tts;
    private final Handler handler = new Handler(Looper.getMainLooper());
    private Runnable timerRunnable;
    private boolean timerRunning;
    private int currentSeconds;
    private int totalSeconds;
    private String currentScreen = "home";

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        getWindow().setStatusBarColor(Color.WHITE);
        getWindow().setNavigationBarColor(Color.WHITE);
        getWindow().getDecorView().setSystemUiVisibility(View.SYSTEM_UI_FLAG_LIGHT_STATUS_BAR);
        prefs = getSharedPreferences("formiva", MODE_PRIVATE);
        migrateOldPreferences();
        tts = new TextToSpeech(this, this);
        if (!prefs.getBoolean("onboarding_done", false)) showOnboarding();
        else showHome();
    }

    private void migrateOldPreferences() {
        SharedPreferences old = getSharedPreferences("forma_donus", MODE_PRIVATE);
        if (!prefs.contains("completed") && old.contains("completed")) {
            prefs.edit().putInt("completed", old.getInt("completed", 0)).apply();
        }
        if (!prefs.contains("days_initialized")) {
            prefs.edit()
                    .putBoolean("day_thu", true)
                    .putBoolean("day_fri", true)
                    .putBoolean("days_initialized", true)
                    .putBoolean("pelvic", true)
                    .apply();
        }
    }

    @Override
    public void onInit(int status) {
        if (status == TextToSpeech.SUCCESS) {
            tts.setLanguage(new Locale("tr", "TR"));
            tts.setSpeechRate(0.90f);
            tts.setPitch(0.98f);
            selectBestTurkishVoice();
        }
    }

    private void selectBestTurkishVoice() {
        if (tts == null || tts.getVoices() == null) return;
        boolean male = isMaleCoach();
        Voice best = null;
        int bestScore = Integer.MIN_VALUE;
        for (Voice voice : tts.getVoices()) {
            Locale locale = voice.getLocale();
            if (locale == null || !"tr".equalsIgnoreCase(locale.getLanguage())) continue;
            String name = voice.getName() == null ? "" : voice.getName().toLowerCase(Locale.ROOT);
            int score = voice.getQuality() * 10;
            if (voice.isNetworkConnectionRequired()) score += 12;
            if (name.contains("neural") || name.contains("premium") || name.contains("natural")) score += 30;
            if (male && (name.contains("male") || name.contains("erkek"))) score += 10;
            if (!male && (name.contains("female") || name.contains("kadın") || name.contains("kadin"))) score += 10;
            if (score > bestScore) {
                bestScore = score;
                best = voice;
            }
        }
        if (best != null) tts.setVoice(best);
    }

    private void speak(String text) {
        if (tts != null) tts.speak(text, TextToSpeech.QUEUE_FLUSH, null, "formiva_coach");
    }

    private boolean isMaleCoach() {
        return !"female".equals(prefs.getString("coach_gender", "male"));
    }

    private int dp(int value) {
        return Math.round(value * getResources().getDisplayMetrics().density);
    }

    private GradientDrawable rounded(int color, float radiusDp) {
        GradientDrawable drawable = new GradientDrawable();
        drawable.setColor(color);
        drawable.setCornerRadius(dp((int) radiusDp));
        return drawable;
    }

    private GradientDrawable outlined(int color, float radiusDp, int strokeColor, int strokeDp) {
        GradientDrawable drawable = rounded(color, radiusDp);
        drawable.setStroke(dp(strokeDp), strokeColor);
        return drawable;
    }

    private GradientDrawable primaryGradient() {
        GradientDrawable drawable = new GradientDrawable(
                GradientDrawable.Orientation.LEFT_RIGHT,
                new int[]{BLUE, CYAN, MINT});
        drawable.setCornerRadius(dp(18));
        return drawable;
    }

    private TextView text(String value, int size, int color, boolean bold) {
        TextView view = new TextView(this);
        view.setText(value);
        view.setTextSize(size);
        view.setTextColor(color);
        view.setLineSpacing(0, 1.08f);
        view.setTypeface(Typeface.create("sans-serif", bold ? Typeface.BOLD : Typeface.NORMAL));
        return view;
    }

    private TextView heading(String value, int size) {
        TextView view = text(value, size, NAVY, true);
        view.setPadding(0, dp(4), 0, dp(4));
        return view;
    }

    private TextView body(String value, int size) {
        TextView view = text(value, size, MUTED, false);
        view.setPadding(0, dp(3), 0, dp(3));
        return view;
    }

    private Button primaryButton(String label) {
        Button button = new Button(this);
        button.setText(label);
        button.setTextColor(Color.WHITE);
        button.setTextSize(16);
        button.setAllCaps(false);
        button.setTypeface(Typeface.create("sans-serif", Typeface.BOLD));
        button.setBackground(primaryGradient());
        button.setStateListAnimator(null);
        LinearLayout.LayoutParams params = new LinearLayout.LayoutParams(
                LinearLayout.LayoutParams.MATCH_PARENT, dp(56));
        params.setMargins(0, dp(8), 0, dp(8));
        button.setLayoutParams(params);
        return button;
    }

    private Button secondaryButton(String label) {
        Button button = new Button(this);
        button.setText(label);
        button.setTextColor(NAVY);
        button.setTextSize(15);
        button.setAllCaps(false);
        button.setTypeface(Typeface.create("sans-serif", Typeface.BOLD));
        button.setBackground(outlined(Color.WHITE, 18, BORDER, 1));
        button.setStateListAnimator(null);
        LinearLayout.LayoutParams params = new LinearLayout.LayoutParams(
                LinearLayout.LayoutParams.MATCH_PARENT, dp(52));
        params.setMargins(0, dp(6), 0, dp(6));
        button.setLayoutParams(params);
        return button;
    }

    private LinearLayout card() {
        LinearLayout card = new LinearLayout(this);
        card.setOrientation(LinearLayout.VERTICAL);
        card.setPadding(dp(18), dp(17), dp(18), dp(17));
        card.setBackground(rounded(Color.WHITE, 22));
        card.setElevation(dp(2));
        LinearLayout.LayoutParams params = new LinearLayout.LayoutParams(
                LinearLayout.LayoutParams.MATCH_PARENT,
                LinearLayout.LayoutParams.WRAP_CONTENT);
        params.setMargins(0, dp(7), 0, dp(7));
        card.setLayoutParams(params);
        return card;
    }

    private LinearLayout pageContent() {
        LinearLayout content = new LinearLayout(this);
        content.setOrientation(LinearLayout.VERTICAL);
        content.setPadding(dp(18), dp(12), dp(18), dp(26));
        content.setBackgroundColor(BG);
        return content;
    }

    private void addBrandHeader(LinearLayout root, boolean compact) {
        FormivaLogoView logo = new FormivaLogoView(this);
        LinearLayout.LayoutParams logoParams = new LinearLayout.LayoutParams(
                LinearLayout.LayoutParams.MATCH_PARENT, dp(compact ? 76 : 96));
        logoParams.setMargins(0, 0, 0, dp(6));
        logo.setLayoutParams(logoParams);
        root.addView(logo);
    }

    private void setScreen(LinearLayout content, int activeTab) {
        LinearLayout shell = new LinearLayout(this);
        shell.setOrientation(LinearLayout.VERTICAL);
        shell.setBackgroundColor(BG);

        ScrollView scroll = new ScrollView(this);
        scroll.setFillViewport(true);
        scroll.setClipToPadding(false);
        scroll.addView(content);
        shell.addView(scroll, new LinearLayout.LayoutParams(
                LinearLayout.LayoutParams.MATCH_PARENT, 0, 1f));

        if (activeTab >= 0) shell.addView(bottomNavigation(activeTab));
        setContentView(shell);
    }

    private LinearLayout bottomNavigation(int active) {
        LinearLayout nav = new LinearLayout(this);
        nav.setOrientation(LinearLayout.HORIZONTAL);
        nav.setGravity(Gravity.CENTER);
        nav.setPadding(dp(6), dp(6), dp(6), dp(7));
        nav.setBackgroundColor(Color.WHITE);
        nav.setElevation(dp(12));
        String[] icons = {"⌂", "▦", "◎", "▥", "◉"};
        String[] labels = {"Ana Sayfa", "Program", "Egzersizler", "Raporlar", "Profil"};
        View.OnClickListener[] actions = {
                v -> showHome(), v -> showProgram(), v -> showLibrary(), v -> showReport(), v -> showProfile()
        };
        for (int i = 0; i < labels.length; i++) {
            LinearLayout item = new LinearLayout(this);
            item.setOrientation(LinearLayout.VERTICAL);
            item.setGravity(Gravity.CENTER);
            item.setPadding(dp(4), dp(4), dp(4), dp(2));
            TextView icon = text(icons[i], 20, i == active ? BLUE : MUTED, true);
            icon.setGravity(Gravity.CENTER);
            TextView label = text(labels[i], 10, i == active ? NAVY : MUTED, i == active);
            label.setGravity(Gravity.CENTER);
            item.addView(icon, new LinearLayout.LayoutParams(dp(34), dp(28)));
            item.addView(label);
            item.setOnClickListener(actions[i]);
            nav.addView(item, new LinearLayout.LayoutParams(0, dp(58), 1f));
        }
        return nav;
    }

    private TextView chip(String label, boolean selected) {
        TextView chip = text(label, 14, selected ? NAVY : MUTED, selected);
        chip.setGravity(Gravity.CENTER);
        chip.setPadding(dp(15), dp(10), dp(15), dp(10));
        chip.setBackground(outlined(selected ? Color.rgb(232, 249, 244) : Color.WHITE,
                16, selected ? MINT : BORDER, selected ? 2 : 1));
        LinearLayout.LayoutParams params = new LinearLayout.LayoutParams(
                LinearLayout.LayoutParams.WRAP_CONTENT, dp(44));
        params.setMargins(dp(3), dp(4), dp(3), dp(4));
        chip.setLayoutParams(params);
        return chip;
    }

    private void updateChip(TextView chip, boolean selected) {
        chip.setTextColor(selected ? NAVY : MUTED);
        chip.setTypeface(Typeface.create("sans-serif", selected ? Typeface.BOLD : Typeface.NORMAL));
        chip.setBackground(outlined(selected ? Color.rgb(232, 249, 244) : Color.WHITE,
                16, selected ? MINT : BORDER, selected ? 2 : 1));
    }

    private LinearLayout metric(String title, String value, String subtitle) {
        LinearLayout item = card();
        item.addView(body(title, 13));
        TextView number = heading(value, 25);
        number.setTextColor(NAVY);
        item.addView(number);
        item.addView(body(subtitle, 12));
        return item;
    }

    private void showOnboarding() {
        currentScreen = "onboarding";
        stopTimer();
        LinearLayout root = pageContent();
        addBrandHeader(root, false);
        root.addView(heading("Size en uygun koçu seçin", 27));
        root.addView(body("Seçiminize göre uygulamadaki manken, yönlendirme dili ve mümkün olduğunda Türkçe ses profili uyarlanır.", 15));

        final boolean[] male = {isMaleCoach()};
        LinearLayout row = new LinearLayout(this);
        row.setOrientation(LinearLayout.HORIZONTAL);
        row.setPadding(0, dp(12), 0, dp(10));

        LinearLayout maleCard = coachCard(true, male[0]);
        LinearLayout femaleCard = coachCard(false, !male[0]);
        row.addView(maleCard, new LinearLayout.LayoutParams(0, dp(250), 1f));
        Space gap = new Space(this);
        row.addView(gap, new LinearLayout.LayoutParams(dp(10), 1));
        row.addView(femaleCard, new LinearLayout.LayoutParams(0, dp(250), 1f));
        root.addView(row);

        View.OnClickListener selectMale = v -> {
            male[0] = true;
            refreshCoachCard(maleCard, true, true);
            refreshCoachCard(femaleCard, false, false);
        };
        View.OnClickListener selectFemale = v -> {
            male[0] = false;
            refreshCoachCard(maleCard, true, false);
            refreshCoachCard(femaleCard, false, true);
        };
        maleCard.setOnClickListener(selectMale);
        femaleCard.setOnClickListener(selectFemale);

        Button continueButton = primaryButton("Devam Et");
        continueButton.setOnClickListener(v -> {
            prefs.edit()
                    .putString("coach_gender", male[0] ? "male" : "female")
                    .putBoolean("onboarding_done", true)
                    .apply();
            selectBestTurkishVoice();
            showHome();
        });
        root.addView(continueButton);
        TextView privacy = body("Koç seçimini Profil ekranından daha sonra değiştirebilirsin.", 12);
        privacy.setGravity(Gravity.CENTER);
        root.addView(privacy);
        setScreen(root, -1);
    }

    private LinearLayout coachCard(boolean male, boolean selected) {
        LinearLayout wrapper = new LinearLayout(this);
        wrapper.setOrientation(LinearLayout.VERTICAL);
        wrapper.setPadding(dp(8), dp(8), dp(8), dp(10));
        wrapper.setGravity(Gravity.CENTER_HORIZONTAL);
        wrapper.setBackground(outlined(Color.WHITE, 24, selected ? MINT : BORDER, selected ? 2 : 1));
        wrapper.setElevation(dp(2));
        CoachAvatarView avatar = new CoachAvatarView(this, male);
        avatar.setSelectedState(selected);
        wrapper.addView(avatar, new LinearLayout.LayoutParams(
                LinearLayout.LayoutParams.MATCH_PARENT, 0, 1f));
        TextView label = heading(male ? "Erkek Koç" : "Kadın Koç", 16);
        label.setGravity(Gravity.CENTER);
        wrapper.addView(label);
        return wrapper;
    }

    private void refreshCoachCard(LinearLayout card, boolean male, boolean selected) {
        card.setBackground(outlined(Color.WHITE, 24, selected ? MINT : BORDER, selected ? 2 : 1));
        if (card.getChildAt(0) instanceof CoachAvatarView) {
            ((CoachAvatarView) card.getChildAt(0)).setSelectedState(selected);
        }
    }

    private void showHome() {
        currentScreen = "home";
        stopTimer();
        LinearLayout root = pageContent();
        addBrandHeader(root, true);

        LinearLayout greeting = new LinearLayout(this);
        greeting.setOrientation(LinearLayout.HORIZONTAL);
        TextView hello = heading("Merhaba! 👋", 24);
        greeting.addView(hello, new LinearLayout.LayoutParams(0, dp(50), 1f));
        TextView bell = text("●", 18, MINT, true);
        bell.setGravity(Gravity.CENTER);
        greeting.addView(bell, new LinearLayout.LayoutParams(dp(44), dp(44)));
        root.addView(greeting);
        root.addView(body("Bugünkü programın hazır. Küçük adımlarla, düzenli ve güvenli ilerle.", 14));

        LinearLayout summary = card();
        summary.addView(heading("Bugünkü Özet", 18));
        LinearLayout summaryRow = new LinearLayout(this);
        summaryRow.setOrientation(LinearLayout.HORIZONTAL);
        ProgressRingView ring = new ProgressRingView(this);
        ring.setValues("12", "Tekrar", 0.72f);
        summaryRow.addView(ring, new LinearLayout.LayoutParams(dp(130), dp(130)));
        LinearLayout stats = new LinearLayout(this);
        stats.setOrientation(LinearLayout.VERTICAL);
        stats.setPadding(dp(14), 0, 0, 0);
        stats.addView(statLine("Antrenman", "245 kcal"));
        stats.addView(statLine("Süre", "35 dk"));
        stats.addView(statLine("İlerleme", "%" + completionScore()));
        summaryRow.addView(stats, new LinearLayout.LayoutParams(0, dp(130), 1f));
        summary.addView(summaryRow);
        root.addView(summary);

        LinearLayout week = card();
        LinearLayout weekTitle = new LinearLayout(this);
        weekTitle.setOrientation(LinearLayout.HORIZONTAL);
        weekTitle.addView(heading("Bu Hafta", 18), new LinearLayout.LayoutParams(0, dp(40), 1f));
        TextView daysText = text(selectedDaysLong(), 12, SUCCESS, true);
        daysText.setGravity(Gravity.CENTER_VERTICAL | Gravity.END);
        weekTitle.addView(daysText, new LinearLayout.LayoutParams(0, dp(40), 1f));
        week.addView(weekTitle);
        LinearLayout days = new LinearLayout(this);
        days.setGravity(Gravity.CENTER);
        for (int i = 0; i < DAY_NAMES.length; i++) {
            boolean selected = prefs.getBoolean(DAY_KEYS[i], false);
            TextView day = text(DAY_NAMES[i], 12, selected ? Color.WHITE : MUTED, selected);
            day.setGravity(Gravity.CENTER);
            day.setBackground(rounded(selected ? (i % 2 == 0 ? BLUE : MINT) : Color.rgb(244, 247, 249), 18));
            LinearLayout.LayoutParams params = new LinearLayout.LayoutParams(0, dp(40), 1f);
            params.setMargins(dp(2), 0, dp(2), 0);
            days.addView(day, params);
        }
        week.addView(days);
        root.addView(week);

        LinearLayout recommended = card();
        TextView badge = text("BANA ÖZEL", 12, SUCCESS, true);
        recommended.addView(badge);
        recommended.addView(heading("Evde Güçlenme Programı", 22));
        recommended.addView(body("Güç, core, kondisyon ve kontrollü pelvik taban desteği seçtiğin günlere göre planlanır.", 14));
        ExerciseVisualView preview = new ExerciseVisualView(this);
        preview.setExercise(1, isMaleCoach());
        recommended.addView(preview, new LinearLayout.LayoutParams(
                LinearLayout.LayoutParams.MATCH_PARENT, dp(190)));
        Button start = primaryButton("Antrenmana Başla");
        start.setOnClickListener(v -> startWorkout(ExerciseData.personalPlan(prefs.getBoolean("pelvic", true))));
        recommended.addView(start);
        root.addView(recommended);

        LinearLayout actions = new LinearLayout(this);
        actions.setOrientation(LinearLayout.HORIZONTAL);
        Button program = secondaryButton("Programı Düzenle");
        program.setOnClickListener(v -> showProgram());
        Button manual = secondaryButton("Manuel Hareket");
        manual.setOnClickListener(v -> showManualSelection());
        actions.addView(program, new LinearLayout.LayoutParams(0, dp(60), 1f));
        Space space = new Space(this);
        actions.addView(space, new LinearLayout.LayoutParams(dp(8), 1));
        actions.addView(manual, new LinearLayout.LayoutParams(0, dp(60), 1f));
        root.addView(actions);

        LinearLayout safety = card();
        safety.addView(heading("Güvenli Başlangıç", 17));
        safety.addView(body("Göğüs ağrısı, ciddi nefes darlığı, baş dönmesi veya keskin ağrı oluşursa antrenmanı durdur.", 13));
        root.addView(safety);
        setScreen(root, 0);
    }

    private LinearLayout statLine(String label, String value) {
        LinearLayout line = new LinearLayout(this);
        line.setOrientation(LinearLayout.HORIZONTAL);
        line.setGravity(Gravity.CENTER_VERTICAL);
        TextView left = body(label, 13);
        TextView right = text(value, 14, NAVY, true);
        right.setGravity(Gravity.END | Gravity.CENTER_VERTICAL);
        line.addView(left, new LinearLayout.LayoutParams(0, dp(38), 1f));
        line.addView(right, new LinearLayout.LayoutParams(0, dp(38), 1f));
        return line;
    }

    private int completionScore() {
        int completed = prefs.getInt("completed", 0);
        return Math.min(100, 42 + completed * 9);
    }

    private String selectedDaysLong() {
        StringBuilder builder = new StringBuilder();
        String[] full = {"Pazartesi", "Salı", "Çarşamba", "Perşembe", "Cuma", "Cumartesi", "Pazar"};
        for (int i = 0; i < DAY_KEYS.length; i++) {
            if (prefs.getBoolean(DAY_KEYS[i], false)) {
                if (builder.length() > 0) builder.append(" • ");
                builder.append(full[i]);
            }
        }
        return builder.length() == 0 ? "Gün seçilmedi" : builder.toString();
    }

    private void showProgram() {
        currentScreen = "program";
        stopTimer();
        LinearLayout root = pageContent();
        addBrandHeader(root, true);
        root.addView(heading("Akıllı Program Oluşturucu", 25));
        root.addView(body("Hedefini, boş günlerini ve ekipmanını seç. Formiva programı doğru sıraya yerleştirsin.", 14));

        LinearLayout goalCard = card();
        goalCard.addView(heading("Hedefin Nedir?", 18));
        String[] goals = {"Güçlenmek", "Kas Kazanmak", "Formda Kalmak", "Kondisyon"};
        final String[] selectedGoal = {prefs.getString("goal", "Güçlenmek")};
        LinearLayout goalsRow = wrappingHorizontal();
        for (String goal : goals) {
            TextView chip = chip(goal, goal.equals(selectedGoal[0]));
            chip.setOnClickListener(v -> {
                selectedGoal[0] = goal;
                refreshChildrenSelection(goalsRow, selectedGoal[0]);
            });
            goalsRow.addView(chip);
        }
        goalCard.addView(goalsRow);
        root.addView(goalCard);

        LinearLayout daysCard = card();
        daysCard.addView(heading("Antrenman Günlerin", 18));
        final boolean[] selectedDays = new boolean[7];
        LinearLayout dayRow = new LinearLayout(this);
        dayRow.setOrientation(LinearLayout.HORIZONTAL);
        for (int i = 0; i < 7; i++) {
            selectedDays[i] = prefs.getBoolean(DAY_KEYS[i], false);
            TextView day = chip(DAY_NAMES[i], selectedDays[i]);
            final int index = i;
            day.setOnClickListener(v -> {
                selectedDays[index] = !selectedDays[index];
                updateChip(day, selectedDays[index]);
            });
            dayRow.addView(day, new LinearLayout.LayoutParams(0, dp(52), 1f));
        }
        daysCard.addView(dayRow);
        root.addView(daysCard);

        LinearLayout preferenceCard = card();
        preferenceCard.addView(heading("Program Tercihleri", 18));
        CheckBox pelvic = new CheckBox(this);
        pelvic.setText("Pelvik taban, gevşeme ve nefes desteğini ekle");
        pelvic.setTextColor(NAVY);
        pelvic.setTextSize(15);
        pelvic.setButtonTintList(new ColorStateList(
                new int[][]{new int[]{android.R.attr.state_checked}, new int[]{}},
                new int[]{MINT, MUTED}));
        pelvic.setChecked(prefs.getBoolean("pelvic", true));
        preferenceCard.addView(pelvic);
        TextView duration = heading("Günlük Süre", 16);
        duration.setPadding(0, dp(12), 0, dp(4));
        preferenceCard.addView(duration);
        String[] durations = {"20 dk", "35 dk", "45 dk"};
        final String[] selectedDuration = {prefs.getString("duration", "35 dk")};
        LinearLayout durationRow = new LinearLayout(this);
        durationRow.setOrientation(LinearLayout.HORIZONTAL);
        for (String value : durations) {
            TextView chip = chip(value, value.equals(selectedDuration[0]));
            chip.setOnClickListener(v -> {
                selectedDuration[0] = value;
                refreshChildrenSelection(durationRow, selectedDuration[0]);
            });
            durationRow.addView(chip, new LinearLayout.LayoutParams(0, dp(52), 1f));
        }
        preferenceCard.addView(durationRow);
        root.addView(preferenceCard);

        Button create = primaryButton("Planımı Oluştur");
        create.setOnClickListener(v -> {
            boolean any = false;
            SharedPreferences.Editor editor = prefs.edit();
            for (int i = 0; i < 7; i++) {
                editor.putBoolean(DAY_KEYS[i], selectedDays[i]);
                any |= selectedDays[i];
            }
            if (!any) {
                Toast.makeText(this, "En az bir antrenman günü seçmelisin.", Toast.LENGTH_LONG).show();
                return;
            }
            editor.putString("goal", selectedGoal[0]);
            editor.putString("duration", selectedDuration[0]);
            editor.putBoolean("pelvic", pelvic.isChecked());
            editor.apply();
            Toast.makeText(this, "Haftalık programın güncellendi.", Toast.LENGTH_SHORT).show();
            showHome();
        });
        root.addView(create);
        setScreen(root, 1);
    }

    private LinearLayout wrappingHorizontal() {
        HorizontalScrollView scroll = new HorizontalScrollView(this);
        scroll.setHorizontalScrollBarEnabled(false);
        LinearLayout row = new LinearLayout(this);
        row.setOrientation(LinearLayout.HORIZONTAL);
        scroll.addView(row);
        LinearLayout holder = new LinearLayout(this);
        holder.setOrientation(LinearLayout.VERTICAL);
        holder.addView(scroll, new LinearLayout.LayoutParams(
                LinearLayout.LayoutParams.MATCH_PARENT, dp(56)));
        return row;
    }

    private void refreshChildrenSelection(LinearLayout row, String selectedText) {
        for (int i = 0; i < row.getChildCount(); i++) {
            View child = row.getChildAt(i);
            if (child instanceof TextView) updateChip((TextView) child, selectedText.contentEquals(((TextView) child).getText()));
        }
    }

    private void showLibrary() {
        currentScreen = "library";
        stopTimer();
        LinearLayout root = pageContent();
        addBrandHeader(root, true);
        root.addView(heading("Egzersizler", 25));
        root.addView(body("Hareketleri görsel manken anlatımıyla incele veya tek hareket olarak başlat.", 14));

        for (int i = 0; i < ExerciseData.NAMES.length; i++) {
            final int index = i;
            LinearLayout item = card();
            item.setOrientation(LinearLayout.HORIZONTAL);
            ExerciseVisualView visual = new ExerciseVisualView(this);
            visual.setExercise(i, isMaleCoach());
            item.addView(visual, new LinearLayout.LayoutParams(dp(116), dp(112)));
            LinearLayout info = new LinearLayout(this);
            info.setOrientation(LinearLayout.VERTICAL);
            info.setPadding(dp(14), 0, 0, 0);
            info.addView(heading(ExerciseData.NAMES[i], 17));
            info.addView(body(ExerciseData.CATEGORIES[i] + " • " + ExerciseData.SECONDS[i] + " sn", 13));
            TextView detail = text("Gör ve Başlat  ›", 13, BLUE, true);
            detail.setPadding(0, dp(10), 0, 0);
            info.addView(detail);
            item.addView(info, new LinearLayout.LayoutParams(0, dp(112), 1f));
            item.setOnClickListener(v -> showExerciseDetail(index));
            root.addView(item);
        }
        Button manual = secondaryButton("Manuel Program Oluştur");
        manual.setOnClickListener(v -> showManualSelection());
        root.addView(manual);
        setScreen(root, 2);
    }

    private void showExerciseDetail(int index) {
        currentScreen = "detail";
        stopTimer();
        LinearLayout root = pageContent();
        addBrandHeader(root, true);
        root.addView(heading(ExerciseData.NAMES[index], 25));
        root.addView(body(ExerciseData.CATEGORIES[index] + " • " + ExerciseData.SECONDS[index] + " saniye", 14));
        ExerciseVisualView visual = new ExerciseVisualView(this);
        visual.setExercise(index, isMaleCoach());
        root.addView(visual, new LinearLayout.LayoutParams(
                LinearLayout.LayoutParams.MATCH_PARENT, dp(310)));
        LinearLayout cue = card();
        cue.addView(heading("Doğru Form", 18));
        cue.addView(body(ExerciseData.CUES[index], 15));
        root.addView(cue);
        Button start = primaryButton("Bu Hareketi Başlat");
        start.setOnClickListener(v -> {
            List<Integer> one = new ArrayList<>();
            one.add(index);
            startWorkout(one);
        });
        root.addView(start);
        setScreen(root, 2);
    }

    private void showManualSelection() {
        currentScreen = "manual";
        stopTimer();
        LinearLayout root = pageContent();
        addBrandHeader(root, true);
        root.addView(heading("Manuel Hareket Seçimi", 24));
        root.addView(body("Seçtiğin hareketler ısınma, güç, core, kondisyon ve nefes sırasına otomatik yerleştirilir.", 14));
        final CheckBox[] checks = new CheckBox[ExerciseData.NAMES.length];
        for (int i = 0; i < ExerciseData.NAMES.length; i++) {
            LinearLayout item = card();
            item.setOrientation(LinearLayout.HORIZONTAL);
            CheckBox check = new CheckBox(this);
            check.setButtonTintList(new ColorStateList(
                    new int[][]{new int[]{android.R.attr.state_checked}, new int[]{}},
                    new int[]{MINT, MUTED}));
            checks[i] = check;
            item.addView(check, new LinearLayout.LayoutParams(dp(46), dp(56)));
            LinearLayout info = new LinearLayout(this);
            info.setOrientation(LinearLayout.VERTICAL);
            info.addView(heading(ExerciseData.NAMES[i], 16));
            info.addView(body(ExerciseData.CATEGORIES[i] + " • " + ExerciseData.SECONDS[i] + " sn", 12));
            item.addView(info, new LinearLayout.LayoutParams(0, dp(64), 1f));
            final CheckBox finalCheck = check;
            item.setOnClickListener(v -> finalCheck.setChecked(!finalCheck.isChecked()));
            root.addView(item);
        }
        Button create = primaryButton("Programı Oluştur ve Başlat");
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
        setScreen(root, 2);
    }

    private void showReport() {
        currentScreen = "report";
        stopTimer();
        LinearLayout root = pageContent();
        addBrandHeader(root, true);
        root.addView(heading("Raporlar", 25));
        root.addView(body("Antrenman devamlılığını ve kişisel gelişimini cihazında takip et.", 14));

        LinearLayout progress = card();
        progress.addView(heading("İlerleme", 18));
        LinearLayout progressRow = new LinearLayout(this);
        progressRow.setOrientation(LinearLayout.HORIZONTAL);
        ProgressRingView ring = new ProgressRingView(this);
        ring.setValues("%" + completionScore(), "Devamlılık", completionScore() / 100f);
        progressRow.addView(ring, new LinearLayout.LayoutParams(dp(150), dp(150)));
        LinearLayout values = new LinearLayout(this);
        values.setOrientation(LinearLayout.VERTICAL);
        values.setPadding(dp(14), 0, 0, 0);
        values.addView(statLine("Toplam Antrenman", String.valueOf(prefs.getInt("completed", 0))));
        values.addView(statLine("Toplam Süre", prefs.getInt("completed", 0) * 35 + " dk"));
        values.addView(statLine("Haftalık Hedef", selectedDayCount() + " gün"));
        progressRow.addView(values, new LinearLayout.LayoutParams(0, dp(150), 1f));
        progress.addView(progressRow);
        root.addView(progress);

        LinearLayout chartCard = card();
        chartCard.addView(heading("Güç ve Dayanıklılık Eğilimi", 18));
        chartCard.addView(body("Son yedi kayıt", 12));
        MiniLineChartView chart = new MiniLineChartView(this);
        chart.setValues(new float[]{0.82f, 0.73f, 0.68f, 0.55f, 0.49f, 0.36f, 0.25f});
        chartCard.addView(chart, new LinearLayout.LayoutParams(
                LinearLayout.LayoutParams.MATCH_PARENT, dp(145)));
        root.addView(chartCard);

        LinearLayout metrics = new LinearLayout(this);
        metrics.setOrientation(LinearLayout.HORIZONTAL);
        LinearLayout weight = metric("Kilo Takibi", "66.0 kg", "Başlangıç kaydı");
        LinearLayout plank = metric("Plank", "30 sn", "Son performans");
        metrics.addView(weight, new LinearLayout.LayoutParams(0, LinearLayout.LayoutParams.WRAP_CONTENT, 1f));
        Space gap = new Space(this);
        metrics.addView(gap, new LinearLayout.LayoutParams(dp(8), 1));
        metrics.addView(plank, new LinearLayout.LayoutParams(0, LinearLayout.LayoutParams.WRAP_CONTENT, 1f));
        root.addView(metrics);

        LinearLayout privacy = card();
        privacy.addView(heading("Gizlilik", 17));
        privacy.addView(body("Rapor verileri bu test sürümünde yalnızca telefonunda saklanır.", 13));
        root.addView(privacy);
        setScreen(root, 3);
    }

    private int selectedDayCount() {
        int count = 0;
        for (String key : DAY_KEYS) if (prefs.getBoolean(key, false)) count++;
        return count;
    }

    private void showProfile() {
        currentScreen = "profile";
        stopTimer();
        LinearLayout root = pageContent();
        addBrandHeader(root, true);
        root.addView(heading("Profil ve Koç Ayarları", 24));
        root.addView(body("Formiva deneyimini kişiselleştir.", 14));

        LinearLayout coach = card();
        coach.addView(heading("Seçili Koç", 18));
        CoachAvatarView avatar = new CoachAvatarView(this, isMaleCoach());
        avatar.setSelectedState(true);
        coach.addView(avatar, new LinearLayout.LayoutParams(
                LinearLayout.LayoutParams.MATCH_PARENT, dp(210)));
        TextView coachName = heading(isMaleCoach() ? "Erkek Koç" : "Kadın Koç", 18);
        coachName.setGravity(Gravity.CENTER);
        coach.addView(coachName);
        Button change = secondaryButton("Koçu Değiştir");
        change.setOnClickListener(v -> showOnboarding());
        coach.addView(change);
        root.addView(coach);

        LinearLayout voice = card();
        voice.addView(heading("Doğal Sesli Anlatım", 18));
        voice.addView(body("Formiva cihazında bulunan en kaliteli Türkçe sesi otomatik seçer. Gerçek insan kaydı için ayrıca profesyonel ses dosyaları eklenmelidir.", 13));
        WaveformView waveform = new WaveformView(this);
        voice.addView(waveform, new LinearLayout.LayoutParams(
                LinearLayout.LayoutParams.MATCH_PARENT, dp(54)));
        Button sample = secondaryButton("Ses Örneğini Dinle");
        sample.setOnClickListener(v -> speak("Merhaba. Ben Formiva koçun. Hareket boyunca nefesini ve formunu birlikte kontrol edeceğiz."));
        voice.addView(sample);
        root.addView(voice);

        LinearLayout about = card();
        about.addView(heading("Formiva", 18));
        about.addView(body("Sürüm 1.1.0 test • Uygulama geliştiricisi MYS Design", 13));
        root.addView(about);
        setScreen(root, 4);
    }

    private void startWorkout(List<Integer> plan) {
        if (plan == null || plan.isEmpty()) {
            Toast.makeText(this, "Program boş.", Toast.LENGTH_LONG).show();
            return;
        }
        showWorkoutStep(plan, 0);
    }

    private void showWorkoutStep(List<Integer> plan, int position) {
        currentScreen = "workout";
        stopTimer();
        int exerciseIndex = plan.get(position);
        totalSeconds = ExerciseData.SECONDS[exerciseIndex];
        currentSeconds = totalSeconds;

        LinearLayout root = pageContent();
        LinearLayout top = new LinearLayout(this);
        top.setOrientation(LinearLayout.HORIZONTAL);
        TextView close = text("×", 30, NAVY, false);
        close.setGravity(Gravity.CENTER);
        close.setOnClickListener(v -> showHome());
        top.addView(close, new LinearLayout.LayoutParams(dp(48), dp(48)));
        LinearLayout topText = new LinearLayout(this);
        topText.setOrientation(LinearLayout.VERTICAL);
        TextView title = heading(ExerciseData.NAMES[exerciseIndex], 21);
        TextView step = body((position + 1) + " / " + plan.size() + " • " + ExerciseData.CATEGORIES[exerciseIndex], 12);
        topText.addView(title);
        topText.addView(step);
        top.addView(topText, new LinearLayout.LayoutParams(0, dp(60), 1f));
        root.addView(top);

        ExerciseVisualView visual = new ExerciseVisualView(this);
        visual.setExercise(exerciseIndex, isMaleCoach());
        root.addView(visual, new LinearLayout.LayoutParams(
                LinearLayout.LayoutParams.MATCH_PARENT, dp(330)));

        LinearLayout timerCard = card();
        timerCard.setGravity(Gravity.CENTER_HORIZONTAL);
        ProgressRingView ring = new ProgressRingView(this);
        ring.setValues(String.valueOf(currentSeconds), "Saniye", 1f);
        timerCard.addView(ring, new LinearLayout.LayoutParams(dp(170), dp(170)));
        int reps = ExerciseData.DEFAULT_REPS[exerciseIndex];
        TextView setText = heading(reps > 0 ? "3 Set • " + reps + " Tekrar" : "Kontrollü tempo • " + totalSeconds + " saniye", 16);
        setText.setGravity(Gravity.CENTER);
        timerCard.addView(setText);
        root.addView(timerCard);

        LinearLayout cueCard = card();
        cueCard.addView(heading("Doğru Form", 18));
        cueCard.addView(body(ExerciseData.CUES[exerciseIndex], 15));
        root.addView(cueCard);

        LinearLayout coachCard = card();
        coachCard.addView(heading("Doğal Sesli Koç", 17));
        LinearLayout coachRow = new LinearLayout(this);
        coachRow.setOrientation(LinearLayout.HORIZONTAL);
        CoachAvatarView avatar = new CoachAvatarView(this, isMaleCoach());
        avatar.setSelectedState(true);
        coachRow.addView(avatar, new LinearLayout.LayoutParams(dp(72), dp(72)));
        LinearLayout voiceInfo = new LinearLayout(this);
        voiceInfo.setOrientation(LinearLayout.VERTICAL);
        voiceInfo.setPadding(dp(12), 0, 0, 0);
        voiceInfo.addView(heading("Harika gidiyorsun!", 15));
        WaveformView waveform = new WaveformView(this);
        voiceInfo.addView(waveform, new LinearLayout.LayoutParams(
                LinearLayout.LayoutParams.MATCH_PARENT, dp(40)));
        coachRow.addView(voiceInfo, new LinearLayout.LayoutParams(0, dp(76), 1f));
        coachCard.addView(coachRow);
        Button listen = secondaryButton("Teknik Anlatımı Dinle");
        listen.setOnClickListener(v -> speak(ExerciseData.NAMES[exerciseIndex] + ". " + ExerciseData.CUES[exerciseIndex]));
        coachCard.addView(listen);
        root.addView(coachCard);

        Button play = primaryButton("Başlat");
        play.setOnClickListener(v -> toggleTimer(play, ring, exerciseIndex));
        root.addView(play);

        LinearLayout controls = new LinearLayout(this);
        controls.setOrientation(LinearLayout.HORIZONTAL);
        if (position > 0) {
            Button previous = secondaryButton("Önceki");
            previous.setOnClickListener(v -> showWorkoutStep(plan, position - 1));
            controls.addView(previous, new LinearLayout.LayoutParams(0, dp(60), 1f));
            Space gap = new Space(this);
            controls.addView(gap, new LinearLayout.LayoutParams(dp(8), 1));
        }
        Button next = secondaryButton(position < plan.size() - 1 ? "Sonraki Hareket" : "Antrenmanı Bitir");
        next.setOnClickListener(v -> {
            if (position < plan.size() - 1) showWorkoutStep(plan, position + 1);
            else finishWorkout();
        });
        controls.addView(next, new LinearLayout.LayoutParams(0, dp(60), 1f));
        root.addView(controls);
        setScreen(root, -1);
        speak("Sıradaki hareket " + ExerciseData.NAMES[exerciseIndex] + ". " + ExerciseData.CUES[exerciseIndex]);
    }

    private void toggleTimer(Button play, ProgressRingView ring, int exerciseIndex) {
        if (timerRunning) {
            stopTimer();
            play.setText("Devam Et");
            return;
        }
        if (currentSeconds <= 0) currentSeconds = totalSeconds;
        timerRunning = true;
        play.setText("Duraklat");
        timerRunnable = new Runnable() {
            @Override
            public void run() {
                if (!timerRunning) return;
                if (currentSeconds > 0) {
                    currentSeconds--;
                    ring.setValues(String.valueOf(currentSeconds), "Saniye", currentSeconds / (float) totalSeconds);
                    if (currentSeconds == 5) speak("Son beş saniye. Formunu koru.");
                    handler.postDelayed(this, 1000);
                } else {
                    timerRunning = false;
                    play.setText("Tamamlandı");
                    speak("Hareket tamamlandı. Kontrollü şekilde dinlen.");
                }
            }
        };
        handler.postDelayed(timerRunnable, 1000);
    }

    private void finishWorkout() {
        stopTimer();
        int completed = prefs.getInt("completed", 0) + 1;
        prefs.edit().putInt("completed", completed).apply();
        speak("Antrenman tamamlandı. Tebrikler. Bugün kendin için güçlü bir adım attın.");
        new AlertDialog.Builder(this)
                .setTitle("Antrenman tamamlandı")
                .setMessage("Antrenmanın rapor ekranına kaydedildi. Devamlılık skorun güncellendi.")
                .setPositiveButton("Raporları Gör", (dialog, which) -> showReport())
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
        if ("home".equals(currentScreen)) super.onBackPressed();
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
package com.mysdesign.formiva;

import android.animation.ValueAnimator;
import android.content.Context;
import android.graphics.Bitmap;
import android.graphics.BitmapFactory;
import android.graphics.Canvas;
import android.graphics.Color;
import android.graphics.LinearGradient;
import android.graphics.Paint;
import android.graphics.Path;
import android.graphics.Rect;
import android.graphics.RectF;
import android.graphics.Shader;
import android.graphics.Typeface;
import android.view.View;
import android.view.animation.AccelerateDecelerateInterpolator;

final class PhotoDraw {
    private PhotoDraw() {}

    static void centerCrop(Canvas canvas, Bitmap bitmap, RectF dst, Paint paint) {
        if (bitmap == null || bitmap.isRecycled()) return;
        float srcRatio = bitmap.getWidth() / (float) bitmap.getHeight();
        float dstRatio = dst.width() / dst.height();
        Rect src;
        if (srcRatio > dstRatio) {
            int srcWidth = Math.round(bitmap.getHeight() * dstRatio);
            int left = (bitmap.getWidth() - srcWidth) / 2;
            src = new Rect(left, 0, left + srcWidth, bitmap.getHeight());
        } else {
            int srcHeight = Math.round(bitmap.getWidth() / dstRatio);
            int top = (bitmap.getHeight() - srcHeight) / 2;
            src = new Rect(0, top, bitmap.getWidth(), top + srcHeight);
        }
        canvas.drawBitmap(bitmap, src, dst, paint);
    }

    static void fitCenter(Canvas canvas, Bitmap bitmap, RectF dst, Paint paint, float scale) {
        if (bitmap == null || bitmap.isRecycled()) return;
        float ratio = Math.min(dst.width() / bitmap.getWidth(), dst.height() / bitmap.getHeight()) * scale;
        float width = bitmap.getWidth() * ratio;
        float height = bitmap.getHeight() * ratio;
        RectF target = new RectF(
                dst.centerX() - width / 2f,
                dst.centerY() - height / 2f,
                dst.centerX() + width / 2f,
                dst.centerY() + height / 2f
        );
        canvas.drawBitmap(bitmap, null, target, paint);
    }
}

class FormivaLogoView extends View {
    private final Paint paint = new Paint(Paint.ANTI_ALIAS_FLAG);

    FormivaLogoView(Context context) {
        super(context);
        setLayerType(LAYER_TYPE_SOFTWARE, null);
    }

    @Override
    protected void onDraw(Canvas canvas) {
        super.onDraw(canvas);
        float w = getWidth();
        float h = getHeight();
        float icon = Math.min(h * 0.76f, w * 0.25f);
        float left = 5f;
        float top = (h - icon) / 2f;

        paint.setStyle(Paint.Style.STROKE);
        paint.setStrokeCap(Paint.Cap.ROUND);
        paint.setStrokeJoin(Paint.Join.ROUND);
        paint.setStrokeWidth(icon * 0.15f);
        paint.setShader(new LinearGradient(left, top, left + icon, top + icon,
                new int[]{Color.rgb(78, 215, 168), Color.rgb(0, 194, 255), Color.rgb(17, 77, 216)},
                null, Shader.TileMode.CLAMP));

        Path f = new Path();
        f.moveTo(left + icon * 0.25f, top + icon * 0.82f);
        f.lineTo(left + icon * 0.25f, top + icon * 0.18f);
        f.lineTo(left + icon * 0.72f, top + icon * 0.18f);
        f.moveTo(left + icon * 0.25f, top + icon * 0.47f);
        f.lineTo(left + icon * 0.62f, top + icon * 0.47f);
        canvas.drawPath(f, paint);

        Path v = new Path();
        v.moveTo(left + icon * 0.49f, top + icon * 0.58f);
        v.lineTo(left + icon * 0.67f, top + icon * 0.84f);
        v.lineTo(left + icon * 0.88f, top + icon * 0.49f);
        canvas.drawPath(v, paint);
        paint.setShader(null);

        float textLeft = left + icon + icon * 0.11f;
        paint.setStyle(Paint.Style.FILL);
        paint.setTypeface(Typeface.create("sans-serif", Typeface.BOLD));
        paint.setTextSize(h * 0.44f);
        paint.setColor(Color.rgb(11, 29, 58));
        canvas.drawText("Formi", textLeft, h * 0.59f, paint);
        float prefix = paint.measureText("Formi");
        paint.setShader(new LinearGradient(textLeft + prefix, 0, textLeft + prefix + h, 0,
                Color.rgb(0, 194, 255), Color.rgb(78, 215, 168), Shader.TileMode.CLAMP));
        canvas.drawText("va", textLeft + prefix, h * 0.59f, paint);
        paint.setShader(null);
        paint.setTypeface(Typeface.create("sans-serif", Typeface.NORMAL));
        paint.setTextSize(h * 0.17f);
        paint.setColor(Color.rgb(102, 112, 133));
        canvas.drawText("Senin Dönüşümün. Senin Koçun.", textLeft, h * 0.86f, paint);
    }
}

class CoachAvatarView extends View {
    private final Paint paint = new Paint(Paint.ANTI_ALIAS_FLAG | Paint.FILTER_BITMAP_FLAG);
    private final Bitmap portrait;
    private boolean selected;

    CoachAvatarView(Context context, boolean male) {
        super(context);
        portrait = BitmapFactory.decodeResource(getResources(),
                male ? R.drawable.male_coach : R.drawable.female_coach);
        setLayerType(LAYER_TYPE_SOFTWARE, null);
    }

    void setSelectedState(boolean selected) {
        this.selected = selected;
        invalidate();
    }

    @Override
    protected void onDraw(Canvas canvas) {
        super.onDraw(canvas);
        float w = getWidth();
        float h = getHeight();
        RectF box = new RectF(3, 3, w - 3, h - 3);
        Path clip = new Path();
        clip.addRoundRect(box, 30, 30, Path.Direction.CW);
        canvas.save();
        canvas.clipPath(clip);
        paint.setColor(Color.rgb(240, 247, 248));
        canvas.drawRect(box, paint);
        PhotoDraw.centerCrop(canvas, portrait, box, paint);

        LinearGradient bottom = new LinearGradient(0, h * 0.60f, 0, h,
                new int[]{Color.TRANSPARENT, Color.argb(125, 11, 29, 58)},
                null, Shader.TileMode.CLAMP);
        paint.setShader(bottom);
        canvas.drawRect(box, paint);
        paint.setShader(null);
        canvas.restore();

        paint.setStyle(Paint.Style.STROKE);
        paint.setStrokeWidth(selected ? 6 : 2);
        paint.setColor(selected ? Color.rgb(78, 215, 168) : Color.rgb(226, 232, 240));
        canvas.drawRoundRect(box, 30, 30, paint);
        paint.setStyle(Paint.Style.FILL);

        if (selected) {
            float cx = w * 0.86f;
            float cy = h * 0.14f;
            float radius = Math.min(w, h) * 0.075f;
            paint.setColor(Color.rgb(78, 215, 168));
            canvas.drawCircle(cx, cy, radius, paint);
            paint.setColor(Color.WHITE);
            paint.setStyle(Paint.Style.STROKE);
            paint.setStrokeCap(Paint.Cap.ROUND);
            paint.setStrokeJoin(Paint.Join.ROUND);
            paint.setStrokeWidth(Math.max(4f, radius * 0.24f));
            Path check = new Path();
            check.moveTo(cx - radius * 0.42f, cy);
            check.lineTo(cx - radius * 0.10f, cy + radius * 0.32f);
            check.lineTo(cx + radius * 0.48f, cy - radius * 0.35f);
            canvas.drawPath(check, paint);
            paint.setStyle(Paint.Style.FILL);
        }
    }
}

class ExerciseVisualView extends View {
    private final Paint paint = new Paint(Paint.ANTI_ALIAS_FLAG | Paint.FILTER_BITMAP_FLAG);
    private final ValueAnimator animator;
    private Bitmap model;
    private int exerciseIndex;
    private float phase;

    ExerciseVisualView(Context context) {
        super(context);
        setLayerType(LAYER_TYPE_SOFTWARE, null);
        animator = ValueAnimator.ofFloat(0f, 1f);
        animator.setDuration(1700);
        animator.setRepeatMode(ValueAnimator.REVERSE);
        animator.setRepeatCount(ValueAnimator.INFINITE);
        animator.setInterpolator(new AccelerateDecelerateInterpolator());
        animator.addUpdateListener(animation -> {
            phase = (float) animation.getAnimatedValue();
            invalidate();
        });
    }

    void setExercise(int exerciseIndex, boolean male) {
        this.exerciseIndex = exerciseIndex;
        model = BitmapFactory.decodeResource(getResources(),
                male ? R.drawable.male_exercise : R.drawable.female_exercise);
        invalidate();
    }

    @Override
    protected void onAttachedToWindow() {
        super.onAttachedToWindow();
        animator.start();
    }

    @Override
    protected void onDetachedFromWindow() {
        animator.cancel();
        super.onDetachedFromWindow();
    }

    @Override
    protected void onDraw(Canvas canvas) {
        super.onDraw(canvas);
        float w = getWidth();
        float h = getHeight();
        RectF area = new RectF(3, 3, w - 3, h - 3);
        Path clip = new Path();
        clip.addRoundRect(area, 34, 34, Path.Direction.CW);
        canvas.save();
        canvas.clipPath(clip);

        paint.setShader(new LinearGradient(0, 0, w, h,
                new int[]{Color.WHITE, Color.rgb(239, 249, 246), Color.rgb(229, 242, 248)},
                null, Shader.TileMode.CLAMP));
        canvas.drawRect(area, paint);
        paint.setShader(null);

        float pulse = 0.96f + phase * 0.035f;
        float lift = (phase - 0.5f) * h * 0.018f;
        canvas.save();
        canvas.translate(0, lift);
        PhotoDraw.fitCenter(canvas, model,
                new RectF(w * 0.06f, h * 0.07f, w * 0.94f, h * 0.87f), paint, pulse);
        canvas.restore();

        paint.setColor(Color.argb(235, 11, 29, 58));
        canvas.drawRoundRect(new RectF(w * 0.06f, h * 0.84f, w * 0.94f, h * 0.96f),
                22, 22, paint);
        paint.setColor(Color.WHITE);
        paint.setTypeface(Typeface.create("sans-serif", Typeface.BOLD));
        paint.setTextSize(Math.max(13f, h * 0.045f));
        String text = exerciseIndex >= 0 && exerciseIndex < ExerciseData.NAMES.length
                ? ExerciseData.NAMES[exerciseIndex] : "Formiva Egzersizi";
        float textWidth = paint.measureText(text);
        canvas.drawText(text, Math.max(w * 0.09f, (w - textWidth) / 2f), h * 0.92f, paint);
        canvas.restore();

        paint.setStyle(Paint.Style.STROKE);
        paint.setStrokeWidth(2);
        paint.setColor(Color.rgb(226, 232, 240));
        canvas.drawRoundRect(area, 34, 34, paint);
        paint.setStyle(Paint.Style.FILL);
    }
}

class ProgressRingView extends View {
    private final Paint paint = new Paint(Paint.ANTI_ALIAS_FLAG);
    private String value = "0";
    private String caption = "";
    private float progress;

    ProgressRingView(Context context) {
        super(context);
    }

    void setValues(String value, String caption, float progress) {
        this.value = value;
        this.caption = caption;
        this.progress = Math.max(0f, Math.min(1f, progress));
        invalidate();
    }

    @Override
    protected void onDraw(Canvas canvas) {
        super.onDraw(canvas);
        float w = getWidth();
        float h = getHeight();
        float size = Math.min(w, h);
        float stroke = size * 0.085f;
        RectF ring = new RectF((w - size) / 2f + stroke, (h - size) / 2f + stroke,
                (w + size) / 2f - stroke, (h + size) / 2f - stroke);
        paint.setStyle(Paint.Style.STROKE);
        paint.setStrokeWidth(stroke);
        paint.setStrokeCap(Paint.Cap.ROUND);
        paint.setColor(Color.rgb(228, 235, 241));
        canvas.drawArc(ring, -90, 360, false, paint);
        paint.setShader(new LinearGradient(ring.left, ring.top, ring.right, ring.bottom,
                Color.rgb(17, 77, 216), Color.rgb(78, 215, 168), Shader.TileMode.CLAMP));
        canvas.drawArc(ring, -90, 360f * progress, false, paint);
        paint.setShader(null);
        paint.setStyle(Paint.Style.FILL);
        paint.setTextAlign(Paint.Align.CENTER);
        paint.setTypeface(Typeface.create("sans-serif", Typeface.BOLD));
        paint.setTextSize(size * 0.25f);
        paint.setColor(Color.rgb(11, 29, 58));
        canvas.drawText(value, w / 2f, h / 2f + size * 0.03f, paint);
        paint.setTypeface(Typeface.create("sans-serif", Typeface.NORMAL));
        paint.setTextSize(size * 0.09f);
        paint.setColor(Color.rgb(102, 112, 133));
        canvas.drawText(caption, w / 2f, h / 2f + size * 0.18f, paint);
        paint.setTextAlign(Paint.Align.LEFT);
    }
}

class WaveformView extends View {
    private final Paint paint = new Paint(Paint.ANTI_ALIAS_FLAG);
    private final ValueAnimator animator;
    private float phase;

    WaveformView(Context context) {
        super(context);
        animator = ValueAnimator.ofFloat(0f, 1f);
        animator.setDuration(1200);
        animator.setRepeatMode(ValueAnimator.REVERSE);
        animator.setRepeatCount(ValueAnimator.INFINITE);
        animator.addUpdateListener(value -> {
            phase = (float) value.getAnimatedValue();
            invalidate();
        });
    }

    @Override
    protected void onAttachedToWindow() {
        super.onAttachedToWindow();
        animator.start();
    }

    @Override
    protected void onDetachedFromWindow() {
        animator.cancel();
        super.onDetachedFromWindow();
    }

    @Override
    protected void onDraw(Canvas canvas) {
        super.onDraw(canvas);
        float w = getWidth();
        float h = getHeight();
        int bars = 22;
        float gap = w / bars;
        paint.setStrokeCap(Paint.Cap.ROUND);
        paint.setStrokeWidth(Math.max(3f, gap * 0.28f));
        paint.setColor(Color.rgb(78, 215, 168));
        for (int i = 0; i < bars; i++) {
            float wave = (float) Math.abs(Math.sin(i * 0.58f + phase * Math.PI * 2));
            float barHeight = h * (0.18f + wave * 0.62f);
            float x = gap * (i + 0.5f);
            canvas.drawLine(x, (h - barHeight) / 2f, x, (h + barHeight) / 2f, paint);
        }
    }
}

class MiniLineChartView extends View {
    private final Paint paint = new Paint(Paint.ANTI_ALIAS_FLAG);
    private float[] values = new float[]{0.3f, 0.45f, 0.42f, 0.6f, 0.7f};

    MiniLineChartView(Context context) {
        super(context);
    }

    void setValues(float[] values) {
        if (values != null && values.length > 1) this.values = values;
        invalidate();
    }

    @Override
    protected void onDraw(Canvas canvas) {
        super.onDraw(canvas);
        float w = getWidth();
        float h = getHeight();
        float left = w * 0.06f;
        float right = w * 0.94f;
        float top = h * 0.12f;
        float bottom = h * 0.85f;

        paint.setStyle(Paint.Style.STROKE);
        paint.setStrokeWidth(1.5f);
        paint.setColor(Color.rgb(232, 237, 242));
        for (int i = 0; i < 4; i++) {
            float y = top + (bottom - top) * i / 3f;
            canvas.drawLine(left, y, right, y, paint);
        }

        Path line = new Path();
        for (int i = 0; i < values.length; i++) {
            float x = left + (right - left) * i / (values.length - 1f);
            float y = bottom - Math.max(0f, Math.min(1f, values[i])) * (bottom - top);
            if (i == 0) line.moveTo(x, y); else line.lineTo(x, y);
        }
        paint.setStrokeWidth(6f);
        paint.setStrokeCap(Paint.Cap.ROUND);
        paint.setStrokeJoin(Paint.Join.ROUND);
        paint.setShader(new LinearGradient(left, 0, right, 0,
                Color.rgb(17, 77, 216), Color.rgb(78, 215, 168), Shader.TileMode.CLAMP));
        canvas.drawPath(line, paint);
        paint.setShader(null);
        paint.setStyle(Paint.Style.FILL);
    }
}
